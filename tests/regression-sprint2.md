# Sprint 2 Regression Test Planı

**Hazırlayan:** QA + DevOps Engineer (Junior-Mid)
**Tarih:** 2026-04-17
**Kapsam:** Sprint 1 + Sprint 2 boyunca yapılan değişikliklerin regresyon
olmadan bir arada çalıştığını kanıtlamak. Gerçek sonuçlar
`tests/regression-report-sprint2.md` dosyasında.

Sprint 2'de eklenen (planlanan) başlıklar:
- **Backend:** `bin/cron/db-backup.php`, Audit trail, Settings, SMS gateway
- **Frontend:** 2FA UI, Settings UI, Validator
- **QA/DevOps (bu çalışma):** `bin/cron/backup-monitor.php`, Prometheus metrics,
  Staging docker-compose, CI staging deploy
- **Tech Lead:** Controller refactor

Sprint 1'den getirdiğimiz pre-existing fail'ler (baseline):
- `HelpersTest::it_formats_money_correctly` — 1 fail (sembol pozisyonu)
- `ValidatorTest::kategori_create_accepts_valid_data` — 1 error (`kategoriler.isim` uyumsuzluğu)

---

## 1. Backup Monitoring (yeni — Sprint 2)

- [ ] `php bin/cron/backup-monitor.php --dry-run` başarıyla çıkar (exit 0)
- [ ] `storage/backups/` yoksa CRITICAL log yazılır, yine de exit 0
- [ ] `bin/cron/db-backup.php` eksikse WARNING satırı düşer
- [ ] Taze (< 25h) backup varsa INFO, 25h-48h arası WARNING, >48h CRITICAL
- [ ] Boyut < 100 KB → WARNING
- [ ] `storage/logs/backup-monitor.log` dosyasına LOCK_EX + FILE_APPEND yazılır
- [ ] Sentry aktifse `captureMessage('warning'|'fatal')` tetiklenir (DSN boşsa no-op)

Komut:
```
php bin/cron/backup-monitor.php --dry-run
php bin/cron/backup-monitor.php --warn=25 --crit=48 --min-size-kb=100
```

## 2. Prometheus Metrics (yeni — Sprint 2)

- [ ] `/api/metrics.php` auth yoksa **401**
- [ ] Yanlış Bearer token → **401**
- [ ] `METRICS_TOKEN` env var boşsa → **503**
- [ ] Doğru token ile **200** ve `text/plain; version=0.0.4` content-type
- [ ] Prometheus text exposition format — `# TYPE` satırları + label'lar doğru
- [ ] Counter, gauge, histogram tipleri üretilir
- [ ] `Metrics::inc`, `Metrics::set`, `Metrics::observe` statik API
- [ ] `pastane_http_requests_total{method,status}` API v1 her istekte artar
- [ ] `pastane_orders_total{durum}` SiparisService::create() sonrası artar
- [ ] `pastane_uptime_seconds` gauge mevcut
- [ ] `Cache-Control: public, max-age=15` header'ı set

## 3. Staging Environment (yeni — Sprint 2)

- [ ] `docker-compose.staging.yml` dosyası mevcut
- [ ] `.env.staging.example` template mevcut (prod değerlerinden farklı)
- [ ] `docs/STAGING_SETUP.md` dokümantasyon mevcut
- [ ] `.github/workflows/ci.yml` → `deploy-staging` job eklendi
- [ ] Compose YAML syntax geçerli (`docker compose config`)
- [ ] Prometheus scrape config (`docker/prometheus-staging.yml`) mevcut

Komut:
```
docker compose --env-file .env.staging.example -f docker-compose.staging.yml config --quiet
```

## 4. Sprint 1 Regresyon Doğrulaması (kritik)

Sprint 1 çalışan özelliklerin Sprint 2'de bozulmadığını doğrula:

- [ ] `php bin/test-sentry.php --dry` hâlâ no-op mesajı veriyor
- [ ] `GET /api/health`, `/api/health/live`, `/api/health/ready` çıktı
      şemaları değişmedi
- [ ] `php bin/cron/log-rotate.php --dry-run` exit 0
- [ ] `bin/cron/masa-timeout.php` değişmedi (paralel agent dokunmadı mı?)

## 5. Admin/Frontend (paralel agent kapsamı — sadece smoke)

- [ ] `/admin/dashboard.php` 200 döner (oturum açık ise)
- [ ] `/admin/login.php` CSP violation yok
- [ ] Yeni eklenen Settings UI (Frontend agent) ise sayfa yüklenir
- [ ] 2FA UI (Frontend agent) — otomatik link/buton erişilebilir

(Detaylı fonksiyonel test paralel agent'ların kendi görevi.)

## 6. Otomatik Test Suite

Sprint 1 baseline: **147 test / 145 pass / 1 fail / 1 error**
Sprint 2'de paralel agent'ların eklediği testler (Backup servis, Audit trail,
Settings, SMS, Controller) yeni assertion getirecek.

- [ ] `vendor/bin/phpunit` — toplam test ≥ 147 (regresyon yok)
- [ ] Yeni fail/error baseline'ın üstünde değil
- [ ] `HelpersTest::it_formats_money_correctly` hâlâ 1 fail (düzeltilmediyse)
- [ ] `ValidatorTest::kategori_create_accepts_valid_data` hâlâ 1 error

## 7. Statik Analiz

- [ ] `vendor/bin/phpstan analyse --no-progress` — error ≤ 3 (Sprint 1 baseline)

## 8. Koşum Komutları (tek seferde)

```bash
# 1) Unit + integration
vendor/bin/phpunit --testdox | tail -40

# 2) Statik analiz
vendor/bin/phpstan analyse --no-progress | tail -20

# 3) Monitoring + cron
php bin/cron/log-rotate.php --dry-run
php bin/cron/backup-monitor.php --dry-run

# 4) Health
curl -sf http://localhost/pastane/api/health/live | jq .

# 5) Metrics (dev token)
curl -sf -H "Authorization: Bearer $(grep METRICS_TOKEN .env | cut -d= -f2)" \
  http://localhost/pastane/api/metrics.php | head
```

---

Gerçek test çıktıları: `tests/regression-report-sprint2.md`
