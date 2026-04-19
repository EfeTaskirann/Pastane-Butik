# Production Deployment Plan — Pastane

**Versiyon:** 1.0
**Tarih:** 2026-04-17 (Sprint 4)
**Sahibi:** Tech Lead
**Onaylayanlar:** Product Owner, QA Lead, DevOps Lead, CISO
**Hedef deploy penceresi:** Salı veya Perşembe, 02:00–04:00 TR (düşük trafik)
**Tahmini downtime:** Blue-Green ile 0 saniye; fallback olarak rolling restart 30–60 saniye.

---

## 1. Özet

Bu belge, Pastane SaaS'inin production'a çıkış sürecini saat-saat bir timeline, önceden yapılacak hazırlıklar, rollback prosedürü, blue-green strateji ilkeleri ve post-deployment doğrulamayla birlikte tanımlar. QA+DevOps agent'ı blue-green altyapısı ve alerting detaylarını ayrıca işlediği için bu doküman **genel prensipler + Tech Lead sorumluluğundaki süreç** üzerine odaklanır.

Sprint 3 sonunda:
- PHPUnit: 181 test / 179 pass / 1 fail / 1 error → **Sprint 4'te 181/181 pass**.
- PHPStan level 5: 3 cosmetic error (kabul edildi, shareholder approval ile).
- Security audit: **0 kritik** (3 yüksek kapatıldı, 6 orta/5 düşük açık ama launch-blocker değil).
- OpenAPI 3.0.3 dokümantasyonu yayında, Swagger UI production'da 404 (env-gate'li).

---

## 2. Pre-Deployment Checklist (T-24h ile T-2h arası)

### 2.1 Kod & Build
- [ ] `main` branch'te tüm PR'lar merge edildi, açık kritik PR yok.
- [ ] `composer.lock` commit edildi, `composer install --no-dev --optimize-autoloader` temiz.
- [ ] `package-lock.json` güncel, frontend build artefact'leri oluşturuldu.
- [ ] CI pipeline son commit'te yeşil (PHPUnit + PHPStan + PHPCS + e2e smoke).
- [ ] Docker image build edildi ve registry'ye push edildi: `pastane:v1.0.0-rc.N`.
- [ ] Image SHA notu ile RUNBOOK'a yapıştırıldı.

### 2.2 Env & Secrets
- [ ] `.env.production` dosyası staging'de bire bir çalıştı.
- [ ] Tüm secret'ler (`DB_PASSWORD`, `APP_KEY`, `JWT_SECRET`, `SMTP_PASSWORD`, `SENTRY_DSN`, `SMS_API_KEY`, `PAYMENT_API_KEY`, `METRICS_TOKEN`, `API_DOCS_TOKEN`) rotate edildi (`docs/SECRET_ROTATION_RUNBOOK.md`).
- [ ] `.env` dosyası production server'da 600 permission + `www-data:www-data` ownership ile.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://pastane.tld` doğrulandı.
- [ ] `.env.example` repo'da güncel (placeholder'larla).

### 2.3 Veritabanı
- [ ] `mysqldump` ile **soğuk** yedek alındı: `backups/pre-deploy-YYYYMMDD-HHMM.sql.gz`.
- [ ] Yedek başka bir sunucuya kopyalandı (3-2-1 kuralı).
- [ ] Yedeği **geri yükleme provası** staging DB'de yapıldı, `tests/restore-dry-run.log` ekte.
- [ ] Migration dry-run: `php bin/migrate.php --pretend` çıktısı incelendi, beklenen diff var.
- [ ] `docs/DB_SCHEMA_AUDIT.md` güncel (51 fark audit olarak biliniyor, launch blocker değil).
- [ ] Rollback için **pre-migration schema snapshot** alındı.

### 2.4 Staging Smoke Test
- [ ] Staging'e production image deploy edildi (`docker-compose.staging.yml`).
- [ ] Manuel smoke test (10 dk, tests/smoke-checklist.md):
  - [ ] Ana sayfa 200 döndü.
  - [ ] Menü sayfası yüklendi, ürünler listelendi.
  - [ ] Admin login + logout akışı çalıştı.
  - [ ] Sipariş oluşturma akışı (public create order) başarılı.
  - [ ] QR menü + masa siparişi akışı.
  - [ ] Ödeme sandbox akışı (gerçek 1 kuruş/iptal).
  - [ ] `/api/health/ready` → 200, `/api/health/live` → 200.
- [ ] Playwright e2e suite staging'e yönlendirildi, tüm critical testler geçti.

### 2.5 Monitoring & Alerting
- [ ] Prometheus scrape config'de production endpoint var.
- [ ] Grafana dashboard "Pastane Prod" paneli güncel.
- [ ] Sentry production project DSN `.env`'de.
- [ ] Alert kuralları (QA+DevOps runbook'undan):
  - `pastane_php_errors_total > 10/min` → Slack #alerts
  - `/api/health/ready HTTP 5xx` → PagerDuty
  - `Disk free < 5GB` → Slack #ops
  - `DB p95 latency > 500ms` → Slack #ops
- [ ] Alert test: staging'de bilerek trigger edildi, **Slack + PagerDuty'de mesaj geldi**.

### 2.6 İnsan & Süreç
- [ ] On-call developer atandı, +6 saat boyunca erişilebilir.
- [ ] Stakeholder sign-off (Product Owner) alındı (email/Slack thread arşivi).
- [ ] Müşteri destek ekibi bilgilendirildi (T-2h).
- [ ] Status page hazır ("Planlı bakım" modu).
- [ ] Rollback karar vericisi belli: **Tech Lead + Product Owner ortak imza**.

---

## 3. Deployment Timeline (Saat-Saat)

Referans saat: **T-0 = 02:00 TR**.

### T-2h (00:00) — Son Hazırlık
1. Team stand-up (15 dk): checklist'i birlikte okuyun.
2. Son bir `git log origin/main..origin/release/v1.0.0` diff kontrolü.
3. Status page'i "Planlı bakım pencere" moduna al.
4. CDN cache warming script'i çalıştır: `make cdn-warm`.
5. Son manuel smoke staging'de.

### T-30m (01:30) — Hazırlık Tamamla
1. Yeni backup al (incremental) — son durum snapshot.
2. Log rotate ve temizleme: `make log-rotate`.
3. Mevcut prod image tag'ini kaydet: `pastane:prev = v0.9.7`.
4. Tüm developer'lar Git ve deploy workflow'undan push hakkını **kısıtlar** (yalnız deploy branch).

### T-0 (02:00) — Deploy Başlat
1. **Blue-Green** aktif ise (QA+DevOps doğrulaması):
   - Green stack'e yeni image deploy (`docker compose -f docker-compose.green.yml up -d`).
   - Green health check: 3 dk boyunca `/api/health/ready` 200 sürekli.
   - Load balancer weight: 0% → 10% → 50% → 100% (her adım 5 dk smoke).
2. Blue-Green yok ise rolling restart:
   - `docker compose pull && docker compose up -d --no-deps --remove-orphans app`
   - 30–60 sn downtime; 503 custom maintenance sayfası devreye girer.
3. Migration çalıştır: `php bin/migrate.php --force` (forward-only, dry-run önceden OK).
4. Cache flush: `make cache-clear` (Redis + OPcache).

### T+15m (02:15) — İlk Doğrulama
1. `/api/health/ready` canary: 3 kez 200.
2. Manuel smoke (public site + admin login).
3. Prometheus metrics: `pastane_http_requests_total` artışı görünüyor mu?
4. Sentry'de yeni hata akışı kontrolü (ilk 15 dk'da yeni issue ≤ 2 olmalı).
5. Hata oranı eşiği: error_rate < %1 → continue. > %1 → **rollback kararı**.

### T+1h (03:00) — Stabilizasyon
1. Grafana "Pastane Prod" dashboard full görünüm kontrol.
2. DB p95 latency < 200 ms doğrula.
3. Public create-order 3 örnek test.
4. Payment sandbox 1 kuruş real flow.
5. Status page "Operational"a geri çek.
6. Stand-up notu Slack #ops'a post edilir (özet + 24h izleme aktif).
7. On-call developer nöbete devam, ekip uyumaya bırakılır.

### T+24h — Retrospektif Pencere Kapanışı
1. `docs/POST_LAUNCH_RUNBOOK.md` 24h izleme kontrol listesi tamamlandı mı?
2. Haftalık retrospektif toplantı düzenle (Cuma).

---

## 4. Rollback Prosedürü

**Rollback kararını Tech Lead + Product Owner birlikte verir.** Kriterler:
- Error rate > %2 (T+0 ile T+30 dk arası süreksiz)
- Health check 5xx > 3 dk
- Critical user path çökmüş (login, checkout, admin dashboard)

### 4.1 Blue-Green Rollback (tercih edilen, 0 downtime)
1. Load balancer'ı 100% Blue'ya çevir (weight = 1, green = 0).
2. Green stack'i durdur ama silme: `docker compose -f docker-compose.green.yml stop`.
3. Incident kaydı aç (`docs/incidents/YYYY-MM-DD-rollback.md`).
4. Sentry'ye manuel event: "Rollback executed at Thh:mm".

### 4.2 DB Migration Rollback (gerekirse)
1. Eğer migration forward-only ise ve Blue server'ı eski şemayla uyumsuz ise:
   - **Yedekten geri yükle** (pre-deploy-YYYYMMDD.sql.gz):
     ```bash
     make db-restore BACKUP=backups/pre-deploy-YYYYMMDD-HHMM.sql.gz
     ```
   - Restore sonrası Blue stack'i restart: `docker compose restart app`.
   - **UYARI**: Restore = post-deploy insert edilen veriler kaybolur. Stakeholder onayı zorunlu.
2. Backward-compatible migration ise DB rollback atlanabilir, sadece image değişir.

### 4.3 Docker Image Rollback
```bash
docker tag pastane:prev pastane:current
docker compose up -d --no-deps app
```
- Önceki image tag'i T-30m'de kaydedildi.
- Post-rollback smoke: manuel + `/api/health/ready`.

### 4.4 İletişim
- Status page → "Partial Outage" → "Investigating" → "Resolved".
- Slack #ops'a post-mortem draft açılır.
- Müşteri destek ekibi bilgilendirilir.

---

## 5. Blue-Green Strateji Prensipleri

> Detay ve altyapı tanımı QA+DevOps agent'ı tarafından `docs/BLUE_GREEN_RUNBOOK.md`'de işlenir. Burada yalnız **Tech Lead perspektifi** var.

### 5.1 Temel İlkeler
- İki özdeş stack: **Blue** (mevcut prod), **Green** (aday).
- Load balancer (nginx/Traefik) weight-based routing: 0/10/50/100 kademeli.
- **Paylaşılan DB** (aynı MySQL instance), uygulama stateless.
- Session storage → Redis (stack değişiminde session kopmaz).
- Environment config identical; yalnız image tag farklı.

### 5.2 Schema Compatibility Kuralı
**Her migration iki versiyon geriye uyumlu olmalı.**
- Kolon ekleme → OK (Blue eski kodu kolon yokmuş gibi çalışır).
- Kolon silme → **iki deploy** (önce kodu temizle, sonra kolonu sil).
- ENUM değişikliği → **geriye dönük set birleşimi** yalnız kullan.
- Default değer değişikliği → PR check'lerde manuel review zorunlu.

### 5.3 Trafik Geçiş Eşikleri
| Kademe | Weight | Süre | İptal koşulu |
|--------|--------|------|--------------|
| Canary | 10% | 5 dk | error_rate > 1% veya p95 > 300ms |
| Half | 50% | 10 dk | error_rate > 0.5% veya p95 > 250ms |
| Full | 100% | 30 dk sabit | error_rate > 0.2% veya p95 > 200ms |

### 5.4 Rollforward vs Rollback
- **Sadece kod fix** ise: yeni green deploy et, tekrar aç.
- **DB ile ilgili** ise: rollback + post-mortem + ikinci deploy attempt ertesi gün.

---

## 6. Post-Deployment Validation Checklist

### 6.1 Functional Smoke (T+0 ile T+15 dk)
- [ ] Ana sayfa (/) → 200, LCP < 2.5s.
- [ ] Menü (/menu) → 200, kategoriler ve ürünler görünür.
- [ ] Sipariş oluştur (POST /api/v1/siparisler) → 201, DB'de kayıt.
- [ ] Admin login (/admin/giris) → 200, session oluştu.
- [ ] Admin dashboard (/admin/dashboard) → istatistikler yüklendi.
- [ ] QR menü (/menu/{masa_kodu}) → 200.
- [ ] 2FA enable/verify akışı (test kullanıcıyla).
- [ ] Rapor sayfası (/admin/raporlar) → 200, chart'lar render oldu.

### 6.2 Non-Functional (T+15 ile T+60 dk)
- [ ] `/api/health/ready` → 200, `status: ok`.
- [ ] `/api/health/live` → 200.
- [ ] `/api/metrics` (Bearer token ile) → Prometheus formatı.
- [ ] Sentry: yeni issue count ≤ 2 (ignorable).
- [ ] Grafana: error_rate < 0.5%, p95 < 200ms.
- [ ] Disk free > 10GB, CPU < 50%, memory < 70%.

### 6.3 İlk 24 Saat Watch
`docs/POST_LAUNCH_RUNBOOK.md` gereği on-call developer izler:
- Her saat başı Grafana dashboard'u inceler.
- Yeni Sentry issue her 15 dk kontrol.
- Disk, DB latency, error_rate trend'leri grafikte.
- Anomali varsa hotfix prosedürü devreye girer.

---

## 7. Contact Tree

| Rol | Kişi | Iletişim | Sorumluluk |
|-----|------|----------|-----------|
| Tech Lead | (Sen) | Slack @techlead | Deploy koordinasyon, rollback kararı |
| Product Owner | PO | Slack @po | Business-level go/no-go |
| On-call Developer | Rotasyon | PagerDuty | İlk 24h hotfix |
| QA Lead | QA | Slack @qa | Smoke test onay |
| DevOps | Ops | Slack @devops | Altyapı, blue-green |
| CISO | Security | Slack @ciso | Secret rotate, audit |

---

## 8. İlgili Dokümanlar

- `docs/GO_NO_GO_CHECKLIST.md` — karar kriterleri
- `docs/POST_LAUNCH_RUNBOOK.md` — 24h izleme
- `docs/SECRET_ROTATION_RUNBOOK.md` — secret rotate
- `docs/SECURITY_AUDIT_SPRINT3.md` — güvenlik bulguları
- `docs/STAGING_SETUP.md` — staging env
- `docs/BLUE_GREEN_RUNBOOK.md` — (QA+DevOps tarafından) altyapı
- `docs/API_VERSIONING.md` — API breaking change süreci

---

## 9. Revizyon Geçmişi

| Tarih | Sürüm | Değişiklik | Yazan |
|-------|-------|-----------|-------|
| 2026-04-17 | 1.0 | İlk sürüm (Sprint 4) | Tech Lead |
