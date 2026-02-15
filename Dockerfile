# Tatlı Düşler - Butik Pasta
# Production Docker Image

# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm ci --only=production

# Copy source and build
COPY assets/ assets/
COPY vite.config.js ./
RUN npm run build

# Stage 2: PHP Application
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        mbstring \
        gd \
        zip \
        opcache

# Enable Apache modules
RUN a2enmod rewrite headers expires

# Configure PHP
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Configure Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . .

# Copy built frontend assets
COPY --from=frontend /app/dist ./dist

# Install Composer dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories
RUN mkdir -p storage/logs storage/cache storage/uploads \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# Remove development files
RUN rm -rf \
    .env.example \
    .git \
    .gitignore \
    docker \
    tests \
    phpunit.xml \
    package*.json \
    vite.config.js \
    assets

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/health/live || exit 1

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
