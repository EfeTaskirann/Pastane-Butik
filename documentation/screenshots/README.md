# Documentation Screenshots

This folder is intentionally shipped empty (or with placeholder files). Each screenshot
referenced by the HTML documentation should be generated from the current running UI and
saved into this folder using the exact file name listed below.

## File format conventions

- **Resolution**: 1200 x 700 pixels (3:1.75 landscape) unless otherwise noted.
- **Encoding**: PNG, 24-bit color, no transparency.
- **Compression**: run each file through `pngquant --quality=80-95` before shipping.
- **File size target**: under 250 KB per image.
- **Device frame**: do not add browser chrome or fake device frames.
  Screenshots should be the raw viewport (use DevTools device mode or
  `playwright page.screenshot({ clip: { ... } })`).
- **Language**: English UI. Switch `APP_LOCALE=en` in `.env` before capturing.
- **Data**: use the seeded demo data (`php bin/db-seed.php`). Do not expose real customer PII.

## Screenshots to produce (grouped by source HTML)

### index.html

| File | Captured URL / View | Notes |
| --- | --- | --- |
| `hero-banner.png` | `/` (public homepage) | Crop the top hero band + first product row. |
| `architecture-overview.png` | Diagram | Create in draw.io or Excalidraw, export as PNG. Shows PHP, MySQL, Redis, Nginx, Sentry. |
| `feature-collage.png` | Composite | 3-panel collage: admin dashboard, public menu (desktop), public menu (mobile). Use GIMP or Figma. |

### installation.html

| File | Captured URL / View | Notes |
| --- | --- | --- |
| `install-requirements.png` | Terminal | Output of `php -v` and `php -m` with required extensions highlighted. |
| `install-seed-output.png` | Terminal | Full output of `php bin/db-seed.php` showing generated admin password. Redact or regenerate the shown password afterwards. |
| `install-first-login.png` | `/admin/login.php` | Fresh install login form. |

### configuration.html

| File | Captured URL / View | Notes |
| --- | --- | --- |
| `config-mail-test.png` | `/admin/ayarlar/mail.php` after clicking "Send test" | Show the green success toast. |
| `config-payment-toggle.png` | `/admin/ayarlar/index.php` Payment tab | Capture while switching from cash to Iyzico. |
| `config-2fa-setup.png` | `/admin/ayarlar/2fa.php` during enrolment | Show QR code and verify input. Regenerate the secret afterwards. |

### admin-guide.html

| File | Captured URL / View | Notes |
| --- | --- | --- |
| `admin-dashboard.png` | `/admin/dashboard.php` | Full dashboard with stat cards and sparkline. |
| `admin-products.png` | `/admin/urunler.php` | Product list with filter bar visible. |
| `admin-categories.png` | `/admin/kategoriler.php` | Show at least 5 categories; drag handle visible. |
| `admin-messages.png` | `/admin/mesajlar.php` | One message expanded with reply composer open. |
| `admin-kitchen.png` | `/admin/mutfak.php` | Show 3-5 order cards in mixed states. |
| `admin-reports.png` | `/admin/raporlar.php` | Current month revenue chart + top products table. |
| `admin-settings-2fa.png` | `/admin/ayarlar/2fa.php` after enrolment | Show backup codes list (one-time display). |

### user-guide.html

| File | Captured URL / View | Notes |
| --- | --- | --- |
| `menu-landing.png` | `/menu` | Desktop viewport, featured carousel + first row of products. |
| `menu-checkout.png` | `/menu` with cart drawer + proceed to checkout | Show filled-in customer details form. |
| `menu-tracking.png` | `/menu/siparis-takip?kod=DEMO123` | Timeline with "Preparing" step active. |
| `menu-qr-scan.png` | Phone + printed QR mockup | Composite of a phone showing the QR-scoped menu and the paper QR next to it. |
| `menu-dark-mode.png` | `/menu` with `html[data-theme="dark"]` | Same layout as `menu-landing.png` but in dark palette for visual comparison. |

### faq.html

| File | Captured URL / View | Notes |
| --- | --- | --- |
| `faq-error-log.png` | Terminal showing `tail -f storage/logs/app.log` | Capture an anonymised PHP stack trace. |
| `faq-backup-screen.png` | `/admin/ayarlar/backup.php` | Show the list of recent backups and the "Backup now" button. |
| `faq-support-ticket.png` | CodeCanyon buyer dashboard | Screenshot of the support ticket composer. Blur any personal data. |

## How to regenerate all screenshots

1. Run `php -S localhost:8080 -t .` from the project root.
2. In a second terminal, run `node tools/capture-screenshots.mjs` (Playwright helper, ships
   under `tools/`). It will iterate through every URL above and write PNGs into this folder.
3. Review each file, then run `pngquant --quality=80-95 --ext .png --force *.png` to shrink.
4. Commit the updated files. They should remain under 250 KB each.

## License / reuse

Screenshots are part of the buyer documentation and carry the same Envato Regular or
Extended License as the rest of the package. Do not redistribute them outside your licensed
end-product.
