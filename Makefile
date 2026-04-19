# =============================================================================
# Pastane — Makefile
#
# Hem Unix (macOS/Linux/WSL) hem de Windows (Git Bash / MSYS2 / MinGW) için
# çalışır. Windows'ta GnuWin32 make veya chocolatey `make` paketi gerekir.
#
# Hızlı başlangıç:
#   make help       -> tüm komutları listele
#   make setup      -> sıfırdan kurulum
#   make serve      -> localhost:8000 aç
# =============================================================================

# OS detection — Windows'ta cp/sed farklı, shell'i bash'e zorla
SHELL              := /usr/bin/env bash
ifeq ($(OS),Windows_NT)
    IS_WINDOWS := 1
    PYTHON     := python
    RM         := rm -rf
else
    IS_WINDOWS := 0
    PYTHON     := python3
    RM         := rm -rf
endif

# Değişkenler
PHP                ?= php
COMPOSER           ?= composer
NPM                ?= npm
DOCKER_COMPOSE     ?= docker compose
SERVE_HOST         ?= localhost
SERVE_PORT         ?= 8000
BACKUP_DIR         ?= storage/backups
DB_NAME            ?= pastane
DB_USER            ?= root
DB_HOST            ?= 127.0.0.1

.DEFAULT_GOAL := help

# =============================================================================
# Help
# =============================================================================
.PHONY: help
help: ## Komut listesi
	@echo ""
	@echo "Pastane — geliştirici komutları"
	@echo "================================"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""

# =============================================================================
# Setup / Bootstrap
# =============================================================================
.PHONY: setup
setup: env composer-install npm-install storage-init migrate ## Sıfırdan kurulum (composer+npm+env+migrate)
	@echo ""
	@echo "Kurulum tamamlandi."
	@echo "  Ana site  : http://$(SERVE_HOST):$(SERVE_PORT)/"
	@echo "  Admin     : http://$(SERVE_HOST):$(SERVE_PORT)/admin/"
	@echo "  Health    : http://$(SERVE_HOST):$(SERVE_PORT)/api/health.php"
	@echo ""

.PHONY: env
env: ## .env dosyasini .env.example'dan olustur (yoksa)
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo ".env olusturuldu (.env.example'dan kopyalandi)."; \
	else \
		echo ".env zaten mevcut, atlandi."; \
	fi

.PHONY: composer-install
composer-install: ## Composer bagimliliklarini yukle
	$(COMPOSER) install --prefer-dist --no-progress

.PHONY: composer-update
composer-update: ## Composer bagimliliklarini guncelle
	$(COMPOSER) update --prefer-dist --no-progress

.PHONY: npm-install
npm-install: ## NPM bagimliliklarini yukle
	$(NPM) ci

.PHONY: storage-init
storage-init: ## storage/ alt klasorlerini olustur
	@mkdir -p storage/cache storage/logs storage/backups storage/sessions uploads
	@echo "storage/ ve uploads/ klasorleri hazir."

# =============================================================================
# Test
# =============================================================================
.PHONY: test
test: ## PHPUnit testlerini calistir
	vendor/bin/phpunit

.PHONY: test-unit
test-unit: ## Sadece Unit testler
	vendor/bin/phpunit --testsuite=Unit

.PHONY: test-feature
test-feature: ## Sadece Feature testler
	vendor/bin/phpunit --testsuite=Feature

.PHONY: test-coverage
test-coverage: ## Coverage raporu olustur (HTML -> coverage/)
	vendor/bin/phpunit --coverage-html coverage --coverage-clover coverage.xml
	@echo "Coverage raporu: coverage/index.html"

.PHONY: test-e2e
test-e2e: ## Playwright E2E testlerini calistir (PHP server gerekli)
	$(NPM) run test:e2e

.PHONY: test-e2e-install
test-e2e-install: ## Playwright browser'lari indir
	$(NPM) install --save-dev @playwright/test
	npx playwright install --with-deps

# =============================================================================
# Lint / Fix
# =============================================================================
.PHONY: lint
lint: lint-php lint-js ## Tum linter'lari calistir (PHP + JS)

.PHONY: lint-php
lint-php: ## PHP linter'lar (phpcs + phpstan + cs-fixer dry-run)
	vendor/bin/phpcs --standard=PSR12 src/ includes/ || true
	vendor/bin/phpstan analyse --no-progress || true
	@if [ -x vendor/bin/php-cs-fixer ]; then \
		vendor/bin/php-cs-fixer fix --dry-run --diff; \
	else \
		echo "php-cs-fixer kurulu degil: composer require --dev friendsofphp/php-cs-fixer"; \
	fi

.PHONY: lint-js
lint-js: ## JS/CSS linter'lar
	$(NPM) run lint

.PHONY: fix
fix: fix-php fix-js ## Tum auto-fix'leri calistir

.PHONY: fix-php
fix-php: ## PHP auto-fix (phpcbf + cs-fixer)
	vendor/bin/phpcbf --standard=PSR12 src/ includes/ || true
	@if [ -x vendor/bin/php-cs-fixer ]; then \
		vendor/bin/php-cs-fixer fix; \
	else \
		echo "php-cs-fixer kurulu degil."; \
	fi

.PHONY: fix-js
fix-js: ## JS/CSS auto-fix (prettier)
	$(NPM) run format

# =============================================================================
# Dev server
# =============================================================================
.PHONY: serve
serve: ## PHP built-in server (localhost:8000)
	@echo "Server: http://$(SERVE_HOST):$(SERVE_PORT)/"
	$(PHP) -S $(SERVE_HOST):$(SERVE_PORT) -t .

.PHONY: dev
dev: ## Vite dev server (asset hot reload)
	$(NPM) run dev

.PHONY: build
build: ## Production asset build (Vite)
	$(NPM) run build

.PHONY: submission-zip
submission-zip: ## CodeCanyon submission paketi: dist/pastane-submission-v*.zip
	$(PHP) bin/build-submission.php

.PHONY: marketing-assets
marketing-assets: ## CodeCanyon marketing görseller: thumbnail, icon, 6 preview
	$(PHP) -d extension=gd bin/build-marketing-assets.php

.PHONY: optimize-images
optimize-images: ## uploads/products/ için WebP conversion (dry-run)
	$(PHP) -d extension=gd bin/optimize-images.php

.PHONY: optimize-images-apply
optimize-images-apply: ## uploads/products/ için WebP conversion (apply)
	$(PHP) -d extension=gd bin/optimize-images.php --apply

.PHONY: regen-database-sql
regen-database-sql: ## database.sql'ı migration state'ten yeniden üret (temp DB)
	$(PHP) bin/regen-database-sql.php --confirm

.PHONY: uninstall
uninstall: ## Tüm kurulumu kaldır — DESTRUCTIVE
	@echo "DESTRUCTIVE — CTRL+C to cancel"
	@sleep 5
	$(PHP) bin/uninstall.php --confirm

# =============================================================================
# Docker
# =============================================================================
.PHONY: docker-up
docker-up: ## Docker Compose up (detached)
	$(DOCKER_COMPOSE) up -d --remove-orphans

.PHONY: docker-down
docker-down: ## Docker Compose down
	$(DOCKER_COMPOSE) down

.PHONY: docker-logs
docker-logs: ## Docker Compose logs (follow)
	$(DOCKER_COMPOSE) logs -f --tail=100

.PHONY: docker-ps
docker-ps: ## Docker container durumu
	$(DOCKER_COMPOSE) ps

.PHONY: docker-build
docker-build: ## Docker image build (no cache)
	$(DOCKER_COMPOSE) build --no-cache

.PHONY: docker-sh
docker-sh: ## Docker app container shell
	$(DOCKER_COMPOSE) exec app sh

# =============================================================================
# Database
# =============================================================================
.PHONY: migrate
migrate: ## Migration'lari calistir
	$(PHP) bin/migrate

.PHONY: migrate-fresh
migrate-fresh: ## DB'yi sifirla ve tum migration'lari yeniden calistir (destructive)
	@echo "UYARI: Bu komut veritabanini SIFIRLAYACAK. 5 saniye icinde Ctrl+C ile iptal edin..."
	@sleep 5
	$(PHP) bin/migrate --fresh

.PHONY: db-backup
db-backup: ## Veritabani yedegi al (storage/backups/)
	@mkdir -p $(BACKUP_DIR)
	@FILE=$(BACKUP_DIR)/backup-$$(date +%Y%m%d-%H%M%S).sql; \
	mysqldump -h $(DB_HOST) -u $(DB_USER) -p $(DB_NAME) > $$FILE && \
	echo "Yedek: $$FILE" && \
	gzip $$FILE && \
	echo "Sikistirildi: $$FILE.gz"

.PHONY: db-restore
db-restore: ## Yedek geri yukle (usage: make db-restore FILE=path.sql[.gz])
	@if [ -z "$(FILE)" ]; then echo "Kullanim: make db-restore FILE=storage/backups/backup-XXX.sql.gz"; exit 1; fi
	@echo "UYARI: Bu komut $(DB_NAME) veritabanini OVERWRITE edecek. 5 sn icinde Ctrl+C..."
	@sleep 5
	@if [ "$${FILE##*.}" = "gz" ]; then \
		gunzip -c $(FILE) | mysql -h $(DB_HOST) -u $(DB_USER) -p $(DB_NAME); \
	else \
		mysql -h $(DB_HOST) -u $(DB_USER) -p $(DB_NAME) < $(FILE); \
	fi
	@echo "Restore tamamlandi."

.PHONY: db-backup-cron
db-backup-cron: ## Cron DB backup script (headless)
	$(PHP) bin/cron/db-backup.php

.PHONY: db-seed
db-seed: ## Ornek veri seed et (idempotent) - 5 kategori + 15 urun + 10 masa + admin + ayarlar
	$(PHP) bin/db-seed.php

.PHONY: cache-warm
cache-warm: ## Kritik cache key'lerini pre-load et (deploy sonrasi)
	$(PHP) bin/cache-warm.php

# =============================================================================
# Bakim
# =============================================================================
.PHONY: clean
clean: ## Cache + dist + coverage temizle
	$(RM) storage/cache/* coverage dist .php-cs-fixer.cache .phpunit.result.cache
	@echo "Cache temizlendi."

.PHONY: clean-all
clean-all: clean ## + vendor + node_modules + playwright report (full reset)
	$(RM) vendor node_modules tests/e2e/report tests/e2e/results
	@echo "Tum artifact'ler silindi. 'make setup' ile yeniden kur."

.PHONY: log-rotate
log-rotate: ## Log rotate cron'u manuel tetikle
	$(PHP) bin/cron/log-rotate.php

.PHONY: cache-clear
cache-clear: ## Uygulama cache'ini temizle
	$(RM) storage/cache/*
	@echo "Cache temizlendi."

# =============================================================================
# Hook'lar
# =============================================================================
.PHONY: hooks-install
hooks-install: ## CaptainHook git hook'larini kur
	@if [ -x vendor/bin/captainhook ]; then \
		vendor/bin/captainhook install -f; \
	else \
		echo "captainhook kurulu degil: composer require --dev captainhook/captainhook"; \
	fi

.PHONY: hooks-validate
hooks-validate: ## captainhook.json gecerli mi?
	vendor/bin/captainhook configuration:validate

# =============================================================================
# CI helpers
# =============================================================================
.PHONY: ci
ci: lint test ## CI pipeline'inin lokal karsiligi (lint + test)

.PHONY: health
health: ## /api/health.php/live endpoint'ini test et
	@curl -fsS http://$(SERVE_HOST):$(SERVE_PORT)/api/health.php/live | $(PYTHON) -m json.tool 2>/dev/null || \
		curl -fsS http://$(SERVE_HOST):$(SERVE_PORT)/api/health.php/live
