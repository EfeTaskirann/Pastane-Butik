# Changelog

All notable changes to **Tatlı Düşler — Butik Pasta & Dessert Ordering System**
are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- `uploads/.htaccess` blocks PHP / script execution across the upload tree (P0-02)
- `bin/uninstall.php --confirm` drops all tables, clears uploads and logs, removes `.env`
- `LICENSE.txt` (Envato Regular / Extended summary), `CREDITS.txt` (full third-party list), `CHANGELOG.md`
- CSS utility class layer (`assets/css/utilities.css`) replacing inline `style=""` attributes
- Buyer-facing documentation under `documentation/` (installation, configuration, admin guide, user guide, FAQ)
- Secret-token order tracking to close the IDOR gap on `/menu/siparis-takip`
- WebP conversion + `loading="lazy"` helper for uploaded product imagery
- Vite production build artefact resolution via `includes/asset.php`
- SVG sanitiser for uploads (`includes/SvgSanitizer.php`)
- Admin empty-state partial (`views/admin/_partials/empty-state.php`)

### Changed
- `.gitattributes` now marks `node_modules/`, `tasks/`, `.agent-logs/`, `CLAUDE.md` as `export-ignore`
- `database.sql` regenerated from current migration state as the canonical install dump
- `declare(strict_types=1)` enforced across legacy `includes/` PHP files

### Fixed
- Production JS bundle no longer leaves `console.log` statements (`terser --drop-console`)
- Submission ZIP builder excludes development-only artefacts

---

## [1.0.0] — 2026-04-20

**First stable CodeCanyon release.** Five-sprint programme completed with zero
inline `onclick`, full dark-mode / WCAG AA coverage, 181/181 unit tests green,
PHPStan level 5 at 0 errors.

### Added
- **Storefront**
  - Responsive QR-menu with category browsing, cart, and checkout
  - Public order tracking (`/menu/siparis-takip`) — now token-secured
  - In-house dining QR flow (`/menu/?masa=…`)
- **Admin panel**
  - Products / categories / orders / messages CRUD
  - Kitchen ("mutfak") and waiter ("garson") live queues with 30 s polling
  - Calendar view with drag-and-drop status updates
  - Tables ("masalar") QR token generation and session tracking
  - Reports (daily / monthly revenue, top products) with CSV / Excel / PDF export
  - Activity log with full audit trail and JSON detail modal
  - Role-based access control (`admin` / `editor` / `viewer`) — 23 permissions
  - Two-factor authentication setup UI (TOTP, Google Chart QR, backup codes)
  - Mail, SMS, backup, and general settings screens with live-preview
- **Security**
  - Central CSP nonce pipeline — every `<script>` carries a per-request nonce
  - Rate limiter with `X-RateLimit-*` headers on all auth endpoints
  - Argon2id / bcrypt dual support for existing hashes, bcrypt (cost 12) for new
  - Session regeneration on login, IP pinning, CSRF tokens on every form
  - MySQL 8 DUAL PASSWORD rotation runbook (`docs/SECRET_ROTATION_RUNBOOK.md`)
- **Infrastructure**
  - Docker Compose stacks for dev / staging / blue / green with healthchecks
  - Blue-green deployment script (`bin/deploy.sh`) with <30 s rollback
  - Prometheus metrics endpoint (`api/metrics.php`) with `METRICS_TOKEN` bearer
  - Sentry native client (no 3rd-party dependency), DSN-driven, PII scrubber
  - Log rotation cron (`bin/cron/log-rotate.php`)
  - Database backup cron + monitoring (`bin/cron/db-backup.php` + `backup-monitor.php`)
- **Developer experience**
  - 181 PHPUnit tests, PHPStan level 5 (0 errors), PHPCS PSR-12
  - Playwright E2E test suite (10 specs, 59 scenarios)
  - GitHub Actions: unit + static analysis + E2E matrix (Chromium + Firefox)
  - `Makefile` with 41 PHONY targets covering setup, test, lint, docker, deploy
  - CaptainHook git hooks: pre-commit lint, conventional-commit msg, pre-push test
  - OpenAPI 3.0.3 specification + Swagger UI gateway (non-production only)
  - `.env.staging.example` and `.env.example` fully documented
- **Accessibility & UX**
  - WCAG AA conformance audit (contrast ≥ 4.5 / 3.0 tracked)
  - Dark mode with `prefers-color-scheme` fallback and manual toggle
  - Skip-links on every top-level page
  - `prefers-reduced-motion` honoured across animations
  - Mobile-first breakpoints at 320 / 375 / 768 px, touch targets ≥ 44×44
  - iOS input zoom fix, dynamic-viewport (`100dvh`) modals

### Infrastructure requirements
- PHP **8.1+** (tested 8.2), MySQL **8.0+**
- Apache 2.4 with `mod_rewrite`, `mod_headers`, `mod_deflate`, `mod_expires`
- Optional: Redis 7.x, Node 18+ (build-time only)

---

## [0.10.0] — 2026-04-17 — Sprint 10 "Code quality polish"

### Changed
- Türkçe terminology consistency pass across admin panel
- PHPDoc blocks added to every public service method
- DRY refactors in validators and repositories
- Extracted magic numbers to named constants

### Fixed
- Typo fixes in user-facing labels and error messages

---

## [0.9.0] — 2026-04-17 — Sprint 9 "Frontend accessibility"

### Added
- `aria-hidden="true"` on every decorative DOM element
- Screen-reader-friendly icon labels
- Throttled scroll handlers (`requestAnimationFrame`, `passive: true`)

### Fixed
- CSS variable leakage across parallel agent work (scoped `:root` overrides)
- Safari `-webkit-backdrop-filter` prefix added
- Hard-coded copyright year replaced with `date('Y')`

---

## [0.8.0] — 2026-04-17 — Sprint 8 "Performance"

### Added
- File + Redis cache layer with `BaseService::cacheRemember()` wrapper
- Service-level invalidation (`UrunService::clearCache()`, cross-service)
- Cache warm-up script (`bin/cache-warm.php`)

### Changed
- N+1 query audits: admin/mutfak, admin/garson now batch kitchen lookups
- Image serving caches reuse `Cache-Control: public, max-age=31536000, immutable`

---

## [0.7.0] — 2026-04-17 — Sprint 7 "Test infrastructure"

### Added
- Unit test baseline with 90 tests
- PHPStan level 5 configuration (`phpstan.neon`)
- PHPCS PSR-12 configuration (`phpcs.xml`)
- Baseline regression report (`tests/baseline-report-sprint0.md`)

---

## [0.6.0] — 2026-04-17 — Sprint 6 "Service layer"

### Added
- Controller / Service / Repository layering under `src/`
- `BaseController`, `BaseService`, `BaseRepository` abstract classes
- Input validators for products, categories, orders
- `Database` singleton with `ALLOWED_TABLES` whitelist

---

## [0.5.0] — 2026-04-17 — Sprint 5 "Error handling"

### Added
- Central error / exception handler (`includes/ErrorHandler.php`)
- Structured JSON error envelope for API responses
- Request-ID propagation

---

## [0.4.0] — 2026-04-17 — Sprint 4 "API standardisation"

### Added
- REST API under `/api/v1/` with JWT authentication
- Route dispatcher (`api/v1/index.php`)
- Rate limiting per endpoint

---

## [0.3.0] — 2026-02-15 — Sprint 3 "Database hardening"

### Added
- Parameterised queries throughout (no direct string concatenation)
- Schema migrations under `database/migrations/`

---

## [0.2.0] — 2026-02-15 — Sprint 2 "Architecture clean-up"

### Added
- Bootstrap entry point (`includes/bootstrap.php`)
- Configuration layer (`config/*.php`) with environment overrides
- Autoload via Composer PSR-4

---

## [0.1.0] — 2026-02-15 — Sprint 1 "Critical security fixes"

### Fixed
- SQL injection vulnerabilities in admin CRUD endpoints
- XSS in contact message rendering
- Session fixation on admin login

---

## [0.0.1] — 2026-02-04 — Initial commit

Initial upload: static site + basic admin CRUD, SQL-level auth.

[Unreleased]: https://codecanyon.net/item/tatli-dusler-butik-pasta/... "Development branch"
[1.0.0]:      https://codecanyon.net/item/tatli-dusler-butik-pasta/... "CodeCanyon v1.0.0"
