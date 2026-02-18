# ===========================================
# Tatlı Düşler - Butik Pasta
# Multi-stage Production Docker Image
# ===========================================

# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend
WORKDIR /build

COPY package*.json ./
RUN npm ci --production=false

COPY assets/ assets/
COPY vite.config.js ./
RUN npm run build

# Stage 2: Install PHP dependencies (separate stage for caching)
FROM composer:2 AS composer
WORKDIR /build

COPY composer.json composer.lock* ./
COPY src/ src/
COPY includes/ includes/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Stage 3: Production runtime
FROM php:8.2-apache AS runtime

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libsodium-dev \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (sodium for Argon2ID password hashing)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        mbstring \
        gd \
        zip \
        opcache \
        sodium

# Enable Apache modules
RUN a2enmod rewrite headers expires

# Configure PHP
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Configure Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files (respects .dockerignore)
COPY --chown=www-data:www-data . .

# Copy Composer vendor from build stage (replaces runtime install)
COPY --from=composer /build/vendor ./vendor

# Copy built frontend assets from frontend stage
COPY --from=frontend /build/dist ./dist

# Create necessary directories with proper permissions
RUN mkdir -p storage/logs storage/cache storage/sessions uploads/products \
    && chown -R www-data:www-data storage uploads \
    && chmod -R 775 storage uploads

# Remove development files from production image
RUN rm -rf \
    .env.example \
    .dockerignore \
    docker/ \
    package*.json \
    vite.config.js \
    node_modules/ \
    assets/

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/api/health/live || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
