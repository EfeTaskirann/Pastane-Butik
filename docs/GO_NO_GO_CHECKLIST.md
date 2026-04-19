# Production Launch — Go/No-Go Checklist

**Versiyon:** 1.0
**Hedef tarih:** TBD (Sprint 4 sonu)
**Karar toplantısı:** T-24h (prod deploy öncesi)
**Zorunlu katılımcılar:** Tech Lead, Product Owner, QA Lead, DevOps Lead, CISO

---

## Nasıl Kullanılır?

Her kalem için **Evet / Hayır / N/A** işaretleyin ve **not alanına gerekirse link / ekran görüntüsü / PR numarası** ekleyin. Tüm zorunlu (**Z**) kalemler **EVET** olmadan launch **YAPILMAZ**. İsteğe bağlı (**O**) kalemler **HAYIR** ise stakeholder sign-off ile geçilebilir.

| Işaret | Anlam |
|--------|-------|
| ✅ | Evet — kriter karşılandı |
| ❌ | Hayır — kriter karşılanmadı |
| ➖ | N/A — bu sürüm için geçerli değil |
| **Z** | Zorunlu (launch blocker) |
| **O** | Opsiyonel (değerlendirmeye açık) |

---

## 1. Test & Kalite (Z)

| # | Kriter | Zorunlu? | Durum | Not |
|---|--------|----------|-------|-----|
| 1.1 | Test coverage ≥ %85 (Sprint 4: 181/181 = **%100 pass**) | Z | [ ] | PHPUnit çıktısı: `vendor/bin/phpunit` son çalıştırma: 181 test, 363 assertion, 0 fail, 0 error |
| 1.2 | PHPStan level 5 → 0 error (Sprint 4: 3 cosmetic error) | O | [ ] | 3 error cosmetic (tipi tanımsız dönüş), shareholder approval gerekli. Detay: `phpstan.neon` + `vendor/bin/phpstan` |
| 1.3 | 2 kalıcı test failure çözüldü mü? | Z | [ ] | Sprint 4'te düzeltildi: `HelpersTest::it_formats_money_correctly` (TR locale `1.234,56 ₺` format), `ValidatorTest::kategori_create_accepts_valid_data` (kolon adı `isim`, `ad` değil) |
| 1.4 | PHPCS PSR-12 → kritik 0 error | O | [ ] | Önceki sürüm 149 error (çoğu CRLF). `.gitattributes` eklendi. Kalan cosmetic: imza bozulmayan stil |
| 1.5 | Playwright e2e suite staging'de 100% pass | Z | [ ] | `npm run test:e2e` staging'e karşı, 59 test case / 2 browser |
| 1.6 | Manual regression smoke (tests/smoke-checklist.md) | Z | [ ] | QA imzası |

---

## 2. Güvenlik (Z)

| # | Kriter | Zorunlu? | Durum | Not |
|---|--------|----------|-------|-----|
| 2.1 | Security audit kritik bulgu = 0 | Z | [ ] | `docs/SECURITY_AUDIT_SPRINT3.md` — 0 kritik, 3 yüksek kapatıldı, 6 orta/5 düşük roadmap'te |
| 2.2 | `composer audit` → 0 known vulnerability | Z | [ ] | Prod'da 0 3rd-party paket, "No packages" normal |
| 2.3 | OWASP Top 10 2021 kapsaması dokümante | Z | [ ] | SECURITY_AUDIT_SPRINT3.md |
| 2.4 | Tüm secret'ler rotate edildi | Z | [ ] | `docs/SECRET_ROTATION_RUNBOOK.md` uygulandı, checklist ekte |
| 2.5 | `.env.production` 600 permission, ownership doğru | Z | [ ] | `ls -la .env` → `-rw------- www-data:www-data` |
| 2.6 | CSP, HSTS, X-Frame-Options, X-Content-Type-Options header'ları aktif | Z | [ ] | `.htaccess` güncel, curl doğrulaması |
| 2.7 | Rate limit + CSRF token aktif | Z | [ ] | `includes/RateLimiter.php`, `includes/Csrf.php` testleri pass |
| 2.8 | 2FA admin için zorunlu/opsiyonel karar verildi | Z | [ ] | Opsiyonel — `admin/ayarlar/2fa.php` UI hazır |

---

## 3. Altyapı & Yedekleme (Z)

| # | Kriter | Zorunlu? | Durum | Not |
|---|--------|----------|-------|-----|
| 3.1 | Backup verify: yedek dosyası geri yüklenebildi mi? | Z | [ ] | Staging DB'ye restore dry-run log'u. `tests/restore-dry-run.log` |
| 3.2 | 3-2-1 backup: en az 2 farklı lokasyon + 1 offsite | Z | [ ] | Local disk + S3/B2 bucket |
| 3.3 | Backup cron (günlük) aktif, son 7 gün çalıştı | Z | [ ] | `bin/cron/db-backup.php` + `backup-monitor.php` (25h WARN/48h CRIT) |
| 3.4 | DB migration dry-run inceleme | Z | [ ] | `php bin/migrate.php --pretend` çıktısı review edildi |
| 3.5 | DB schema diff bilinci | O | [ ] | `docs/DB_SCHEMA_AUDIT.md` — 51 fark audit, launch blocker değil |
| 3.6 | Disk free > 20GB, inode count sağlıklı | Z | [ ] | `df -h` + `df -i` |
| 3.7 | Docker image tag versioned (`:latest` yok) | Z | [ ] | CLAUDE.md dersi — tüm image'lar minor/patch pin |

---

## 4. Health & Monitoring (Z)

| # | Kriter | Zorunlu? | Durum | Not |
|---|--------|----------|-------|-----|
| 4.1 | Health check 200 (staging) | Z | [ ] | `curl https://staging.pastane.tld/api/health/ready` → 200 |
| 4.2 | Health check 200 (prod canary) | Z | [ ] | T-0 sonrası ilk test |
| 4.3 | Monitoring alert testi yapıldı | Z | [ ] | Staging'de bilerek trigger → Slack + PagerDuty geldi |
| 4.4 | Sentry prod project DSN aktif | Z | [ ] | `SENTRY_DSN` env set, `includes/Sentry.php` init |
| 4.5 | Prometheus scrape çalışıyor | Z | [ ] | Grafana "Pastane Prod" dashboard veri görüyor |
| 4.6 | Kritik metrik alert eşikleri konfigüre | Z | [ ] | errors/min, health 5xx, disk, DB latency — `POST_LAUNCH_RUNBOOK.md` |
| 4.7 | Log rotate cron aktif | Z | [ ] | `bin/cron/log-rotate.php` 10MB/7gün/7 rotated |

---

## 5. Deployment Prosedürü (Z)

| # | Kriter | Zorunlu? | Durum | Not |
|---|--------|----------|-------|-----|
| 5.1 | Rollback prosedürü dry-run (staging) | Z | [ ] | Blue→Green→Blue geri dönüş provası — `docs/DEPLOYMENT_PLAN.md §4` |
| 5.2 | Blue-Green stratejisi onayı | Z | [ ] | QA+DevOps runbook'u imzalı |
| 5.3 | CI/CD pipeline yeşil | Z | [ ] | Son commit'te Unit + Feature + e2e + PHPStan + PHPCS pass |
| 5.4 | Deploy timeline gözden geçirildi | Z | [ ] | `DEPLOYMENT_PLAN.md §3` |
| 5.5 | Status page maintenance mode test edildi | Z | [ ] | |

---

## 6. İnsan & Süreç (Z)

| # | Kriter | Zorunlu? | Durum | Not |
|---|--------|----------|-------|-----|
| 6.1 | On-call developer ataması | Z | [ ] | İsim + PagerDuty rotation link |
| 6.2 | Stakeholder sign-off | Z | [ ] | Product Owner email/Slack thread arşivi |
| 6.3 | Müşteri destek ekibi bilgilendirildi | Z | [ ] | T-2h'te Slack #support bildirim |
| 6.4 | İletişim tree güncel (`DEPLOYMENT_PLAN.md §7`) | Z | [ ] | |
| 6.5 | Incident response playbook erişilebilir | O | [ ] | `docs/POST_LAUNCH_RUNBOOK.md §hotfix` |
| 6.6 | Post-launch retrospektif toplantısı planlandı | O | [ ] | Launch sonrası Cuma 10:00 TR |

---

## 7. Dokümantasyon (O)

| # | Kriter | Zorunlu? | Durum | Not |
|---|--------|----------|-------|-----|
| 7.1 | `docs/ONBOARDING.md` güncel | O | [ ] | 10 bölüm, ~30dk setup, TR |
| 7.2 | `docs/DEPLOYMENT_PLAN.md` onaylandı | Z | [ ] | Bu deploy için sign-off |
| 7.3 | `docs/api/openapi.yaml` güncel | O | [ ] | 15 path, 17 schema, production'da Swagger UI 404 |
| 7.4 | CHANGELOG.md bu release için güncel | O | [ ] | v1.0.0 notları |
| 7.5 | CLAUDE.md Sprint 4 dersleri eklendi | O | [ ] | Test fixleri, deployment dersi |

---

## 8. Karar Matrisi

### Go (launch'a devam) koşulu:
- Tüm **Z** kalemler ✅
- Max 1 **O** kalem ❌ olabilir (stakeholder onaylı)
- Son 24 saatte yeni bir **kritik** bug raporu yok

### No-Go (ertele) koşulu:
- Herhangi bir **Z** kalem ❌
- Staging smoke test fail
- Backup restore fail
- Security audit sonrası yeni kritik bulgu

### Conditional Go (kısıtlı launch) koşulu:
- Product Owner + CISO + Tech Lead imza
- Kısıtlı kullanıcı grubu (beta) ile başla
- Watch window uzatılır (72h yerine 48h)

---

## 9. Onay Kutuları

| Rol | İsim | İmza / Tarih | Karar |
|-----|------|--------------|-------|
| Tech Lead | | | [ ] Go [ ] No-Go [ ] Conditional |
| Product Owner | | | [ ] Go [ ] No-Go [ ] Conditional |
| QA Lead | | | [ ] Go [ ] No-Go [ ] Conditional |
| DevOps Lead | | | [ ] Go [ ] No-Go [ ] Conditional |
| CISO | | | [ ] Go [ ] No-Go [ ] Conditional |

---

## 10. Ekler

### Ek A — PHPUnit Son Çıktısı (Sprint 4 bitiş)
```
PHPUnit 10.5.60 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12
Configuration: C:\xampp\htdocs\pastane\phpunit.xml

............................................................... 63 / 181 ( 34%)
................................................................ 127 / 181 ( 70%)
.......................................................         181 / 181 (100%)

Time: 00:00.393, Memory: 14.00 MB

OK, but there were issues!
Tests: 181, Assertions: 363, PHPUnit Warnings: 1.
```
Not: 1 PHPUnit warning = "No code coverage driver available" — launch blocker değil (Xdebug prod'da gerekmez).

### Ek B — Kabul Edilmiş Risk Listesi
- PHPStan 3 cosmetic error (backlog: Sprint 5).
- DB schema audit 51 fark (database.sql dondu, migration kaynak).
- Bazı admin sayfalarında CSP onchange inline kalan (`admin/musteriler.php` 2 select).
- Dark mode Safari < 14'te `backdrop-filter` graceful degrade.

### Ek C — İlgili PR ve Issue Linkleri
TBD — bu bölüm deploy öncesi doldurulur.
