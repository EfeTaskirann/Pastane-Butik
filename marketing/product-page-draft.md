# CodeCanyon Product Page Draft

## Tagline (max 60 chars)

Tatli Dusler — Complete QR Menu, Admin Panel, Kitchen Display for PHP 8.1+

## 5 USP bullets

- Modern stack: PHP 8.1 + MySQL 8 + Vite + Vanilla JS (no framework lock-in)
- In-house dining flow: QR-per-table, kitchen/waiter live queues with 30 s polling
- Security baked in: CSP nonce pipeline, RBAC (3 roles / 23 permissions), 2FA, rate limiting
- A11y & dark mode: WCAG AA conformance, prefers-color-scheme support, mobile-first responsive
- Production-ready DevOps: Docker Compose (dev/staging/blue/green), Prometheus metrics, Sentry, blue-green deploy < 30 s rollback

## Long description

A complete butik pasta / dessert ordering system for single-location cafes and
small chains. Ships with:

- Responsive QR-menu (cart, checkout, order tracking via signed token)
- Admin panel: products / categories / orders / messages / calendar / tables
- Kitchen and waiter live queues (30 s polling, drag-drop status updates)
- Reports: daily / monthly revenue, CSV / Excel / PDF export
- Activity log with full audit trail + JSON detail modal
- 2FA (TOTP + backup codes), mail / SMS / backup / general settings
- Prometheus metrics, Sentry native client, log rotation cron
- Blue-green deployment script with < 30 s rollback
- 181 PHPUnit tests, PHPStan level 5 (0 errors), Playwright E2E (59 scenarios)

## Feature list (flat)

Storefront
- QR-menu
- Cart + checkout
- Token-secured order tracking
- In-house QR flow (scan table QR to order)

Admin
- Dashboard + live KPIs
- Products, categories (hierarchical)
- Orders, kitchen, waiter queues
- Calendar view with drag-drop
- Messages (contact form)
- Tables (QR generation)
- Reports (CSV / Excel / PDF)
- Activity log

Security
- RBAC (admin / editor / viewer)
- 2FA (TOTP)
- CSP nonce on every script
- Rate limiting on all auth endpoints
- Argon2id / bcrypt password hashes

Infrastructure
- Docker Compose (dev, staging, blue, green)
- Prometheus metrics
- Sentry client
- Backup cron + restore procedure
- Log rotation
- Blue-green deployment
- Health checks (/api/health/live, /ready)

DevEx
- Makefile with 41 targets
- Playwright E2E
- PHPUnit + PHPStan + PHPCS
- CaptainHook pre-commit hooks
- OpenAPI 3.0.3 spec + Swagger UI