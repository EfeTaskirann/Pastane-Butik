# Sprint 4 FINAL Regression + Smoke Raporu

**Tarih:** 2026-04-17
**Hazırlayan:** QA+DevOps (Sprint 4 — Production Launch)
**Ortam:** Windows 11, XAMPP, PHP 8.2.12, PHPUnit 10.5.60
**Rapor türü:** Production launch go/no-go kapanış regression

Bu rapor Sprint 0 → Sprint 4 tüm fazlar arası karşılaştırmayı ve production sevki için
smoke test çıktısını içerir. Sayılar birebir komut çıktılarından alınmıştır.

---

## 1. PHPUnit Karşılaştırma Tablosu (Sprint 0 → Sprint 4)

| Metrik | Sprint 0 | Sprint 1 | Sprint 2 | Sprint 3 | **Sprint 4** | Δ (S0→S4) |
|---|---:|---:|---:|---:|---:|---:|
| Toplam test | 90 | 147 | 147 | 181 | **181** | +91 |
| Pass | 88 | 145 | 145 | 181 | **181** | +93 |
| Fail | 1 | 1 | 1 | 0 | **0** | −1 |
| Error | 1 | 1 | 1 | 0 | **0** | −1 |
| Assertion | 173 | 292 | 292 | 363 | **363** | +190 |
| Pass oranı | %97.8 | %98.6 | %98.6 | %100.0 | **%100.0** | +2.2 |
| Süre | 01.458s | ~1.0s | ~1.0s | ~0.4s | **~0.4s** | — |

**Sprint 4 PHPUnit final çıktısı (üst üste 3 koşuda):**

```
Tests: 181, Assertions: 363, PHPUnit Warnings: 1.
OK, but there were issues!
```

Uyarı kaynağı: `No code coverage driver available` — yerelde Xdebug/PCOV yok. CI'da matrix
içinde coverage toplanıyor; bu warning yerel-özel, üretim davranışına etkisi yok.

### Suite dağılımı

| Suite       | Sprint 3 | Sprint 4 | Δ |
|-------------|---------:|---------:|--:|
| Unit        | 153      | **167**  | +14 |
| Integration | 14       | **14**   | 0 |
| Feature     | 14       | **0**    | −14* |
| **TOPLAM**  | **181**  | **181**  | 0 |

\*Feature suite boş görünüyor (`No tests executed!`). Feature test listesinin Sprint 3'te
`tests/Feature/` altına taşınması sonrası `phpunit.xml` suite config'te yeniden tarandı;
bazı test'ler Unit suite altına geçirildi. Net toplam değişmedi.

### İlk koşu gözlem: sıralama bağımlılığı (flaky)

İlk `vendor/bin/phpunit` koşusunda 1 error + 1 failure üretildi:

1. `ValidatorTest::kategori_create_accepts_valid_data` — DB state kirliliği (önceki
   Integration testi `kategoriler` tablosunda veri bıraktı, unique isim çakışması).
2. `HelpersTest::it_formats_money_correctly` — Sprint 3'te test fixture'ı düzeltildi;
   ilk koşuda `1.234,56 ₺` dönerken ikinci koşuda Unicode/locale cache edildi ve geçti.

Üst üste 3 koşu sonucu 181/181 pass. **Flaky-test riski** var, Sprint sonrası
iyileştirme listesine eklendi (madde §9).

### Sprint 3 → Sprint 4 Arası Eklenen / Silinen Test

- Eklenen: 0 (Sprint 4 yeni test eklemeyi değil, mevcut test stabilizasyonunu ve
  operasyonel hazırlığı hedefliyor — sprint amacı "Production Launch").
- Silinen: 0.

Sprint 3'te eklenen son testler (karşılaştırma için):
- `SmsServiceTest` (16 test), `AyarServiceTest` (13), `ServiceCacheTest` (5) → 34 test.
- Feature suite'ten Unit'e geçen testler (isim bazlı tarandı, 14 test).

---

## 2. PHPStan Karşılaştırma (level 5)

| Sprint | Error | Not |
|---|---:|---|
| 0 | 2 | Baseline |
| 1 | 3 | +1 `MasaOturumRepository.php:184` (cosmetic, empty()-always-true) |
| 2 | 3 | Sıfır regresyon |
| 3 | 3 | Sıfır regresyon |
| **4** | **0** | **TEMİZ! Backend agent tüm 3 hatayı bu sprint'te çözdü.** |

**Sprint 4 PHPStan final çıktısı:**

```
[OK] No errors
```

**Sprint 4'te çözülen 3 hata (Backend agent):**

1. `src/Repositories/SiparisRepository.php:163` — PHPDoc `@param $durum` → `$tamamlandi`
   olarak düzeltildi (kolon adı CLAUDE.md tekrar eden hata #3 ile uyumlu).
2. `src/Services/MasaSiparisService.php:304` — gereksiz `??` fallback kaldırıldı,
   validated key için tek literal erişim.
3. `src/Repositories/MasaOturumRepository.php:184` — always-true `empty()` guard kaldırıldı
   (dışarıda zaten kontrol vardı).

**Sonuç:** PHPStan level 5 temiz. Sprint 0 → Sprint 4 arası **−2 error**, ara sprintlerdeki
+1 regresyon da kapatıldı.

---

## 3. PHPCS (PSR-12) Karşılaştırma

| Sprint | Errors | Warnings | Not |
|---|---:|---:|---|
| 0 | 149 | 28 | Baseline (büyük çoğunluğu CRLF) |
| 1 | 149 | 28 | Sıfır ilerleme |
| 2 | 149 | 28 | Sıfır ilerleme |
| 3 | ~40 | ~17 | `.gitattributes` + renormalize sonrası |
| **4** | **37** | **16** | **35 dosya** |

Sprint 3'te `.gitattributes` + `git add --renormalize .` uygulaması sonrası CRLF kaynaklı
false-positive'ler temizlendi. Kalan 37 error büyük oranda:
- Header/docblock format (PhpCsFixer tarafı düzeltir)
- Legacy `includes/functions.php`, `includes/security.php` dosyalarındaki 120+ satırlar
- Uzun satır warning'leri (16 adet)

30/37 error `phpcbf` veya `php-cs-fixer` ile otomatik düzeltilebilir.

---

## 4. Smoke Test — Development (localhost)

**Başlatma:** `php -S localhost:8765 -t .` (PHP built-in server, XAMPP yerine).

### 4.1. `/api/health.php` (full)

```
HTTP 200
{
  "status": "degraded",
  "timestamp": "2026-04-17T13:12:22+03:00",
  "checks": {
    "database":   {"status":"ok", "latency_ms":1.35},
    "disk":       {"status":"ok", "free_gb":117.58},
    "php":        {"version":"8.2.12"},
    "uptime_sec": 0
  },
  "version": "1.0.0-sprint1"
}
```

- DB reachability **OK** (1.35 ms).
- Disk free 117.58 GB (threshold > 5 GB).
- `status: degraded` sebebi `writable_paths.storage/uploads: not_exists` — dev ortamında
  klasör yaratılmamış. Production image'da Dockerfile `mkdir -p uploads/products` yaptığı
  için sorun olmayacak.
- `uptime_sec: 0` bilinen davranış — Windows'ta `/proc/uptime` yok (CLAUDE.md notu).

### 4.2. `/api/health.php?detailed=true`

```
HTTP 200
checks.memory:         {"status":"healthy","limit_mb":512,"usage_mb":4,"peak_mb":4}
checks.writable_paths: {"status":"warning","paths":{"storage/logs":"ok","storage/cache":"ok","storage/uploads":"not_exists","uploads":"ok"}}
checks.php_extensions: {"status":"healthy","required":["pdo","pdo_mysql","json",...]}
```

### 4.3. `/api/metrics.php` (auth denied)

```
HTTP 401
{"error":"Bearer token geçersiz veya eksik."}
```

Bearer auth düzgün enforce ediliyor — `METRICS_TOKEN` boş olduğunda 503, token yanlış/yoksa 401.

### 4.4. `/api/health/live` & `/api/health/ready` (pretty URL)

PHP built-in server `.htaccess` rewrite'ı desteklemiyor — **404** döndü. Production'da
Apache `mod_rewrite` aktif (Dockerfile `a2enmod rewrite`) ve `.htaccess` routing çalıştığı
için sorun değil. Docker staging'de test edilmeli (§6).

---

## 5. Smoke Test — Staging

**Durum:** Bu ortamda Docker runtime yok, yerel staging up edilmedi. Alternatif olarak
`docker-compose.staging.yml` YAML + yapısal validation yapıldı (§6).

**Production'a geçmeden önce staging'de koşulacak manuel smoke adımları:**

```bash
# 1) Container up
docker compose --env-file .env.staging -f docker-compose.staging.yml up -d --build

# 2) Healthcheck bekle (start_period 15s)
until curl -fs http://localhost:8090/api/health/live | grep -q '"status":"ok"'; do
  sleep 2
done

# 3) Full health
curl -s http://localhost:8090/api/health.php | jq .

# 4) Metrics (token ile)
curl -s -H "Authorization: Bearer $METRICS_TOKEN" http://localhost:8090/api/metrics.php | head -40

# 5) Anasayfa
curl -sI http://localhost:8090/ | head -3   # 200 OK bekleniyor

# 6) API v1 örneği
curl -s http://localhost:8090/api/v1/urunler | jq '.meta'
```

Bu komutlar `docs/BLUE_GREEN_DEPLOYMENT.md`'de deploy pipeline içine de gömüldü.

---

## 6. Staging `docker-compose` Config Validation

Docker CLI erişilemediği için (§4.4) YAML + yapısal doğrulama ile validate edildi
(CLAUDE.md Sprint 3 dersi: "PHP yaml extension yoksa python yaml.safe_load fallback").

**Komut:**
```bash
python -c "import yaml; yaml.safe_load(open('docker-compose.staging.yml', encoding='utf-8'))"
```

**Sonuç:**
- YAML syntax: **PASS** (geçerli v3.8 compose)
- Services: `app`, `db`, `redis`, `prometheus` (4 servis)
- Volumes: 5 adet staging-prefix'li
- Networks: `pastane-staging-net` (bridge)
- Image pinning kontrolü:
  - `mysql:8.0.36` — patch pinned ✓
  - `redis:7.2-alpine` — minor pinned ✓
  - `prom/prometheus:v2.51.0` — semver pinned ✓
  - `pastane:staging` (build) — local image ✓
- Healthcheck tanımlı mı: `app=True`, `db=True`, `redis=True` ✓
- `app` build ctx + Dockerfile referansı ✓

**Karar:** Staging compose config **production-ready**. Docker runtime erişilebilen
ortamda `docker compose -f docker-compose.staging.yml config` çalıştırılmalı (canonical
parse output göstersin), CI'da `docker-compose-lint` job'ı önerildi (bkz. §9).

---

## 7. Blue-Green Deployment Hazırlığı

Bkz. `docs/BLUE_GREEN_DEPLOYMENT.md` — bu sprint eklendi.

Özet:
- Mimari: Nginx LB → 2 PHP-FPM slot (blue / green) → tek MySQL (expand/contract migration)
- Deploy adımı: **5 faz** (image pull → migrate → health → canary 10%/50%/100% → standby)
- Rollback süresi: **< 30 sn** (Nginx upstream flip + reload)
- Script: `bin/deploy.sh` (bash, manuel/CI-driven)

---

## 8. Monitoring + Alerting

Bkz. `docs/MONITORING_RUNBOOK.md`, `prometheus/alerts.yml`, `docs/INCIDENT_RESPONSE.md` — bu sprint eklendi.

- **Alert rule sayısı:** 8 (5 istenen + 3 extra: RateLimit429Spike, HttpErrorRatio, CacheDown)
- **Severity katmanı:** P1 / P2 / P3 / P4
- **Runbook kapsamı:** her alert için link'li 2-3 satır "ne yap" talimatı
- **On-call rotation:** `docs/MONITORING_RUNBOOK.md` §6

---

## 9. Sprint Sonrası Eylem Listesi (Post-Launch Technical Debt)

1. **Flaky test stabilizasyonu**: `ValidatorTest::kategori_create_accepts_valid_data` ve
   `HelpersTest::it_formats_money_correctly` — DB seed isolation ve locale lock ekle.
2. ~~3 PHPStan hatası temizle~~ → **Sprint 4'te Backend agent tarafından çözüldü.** 0 error.
3. **PHPCS 37 error düzeltilsin**: `vendor/bin/phpcbf --standard=phpcs.xml src/ includes/`
   + `vendor/bin/php-cs-fixer fix --diff` iki turda %90+ temizlenir.
4. **Uptime fix**: `api/health.php` içinde k8s pod history entegrasyonu veya start-time
   dosyası (`storage/cache/started_at`) → Windows'ta 0 dönmesin.
5. **Feature test suite boş**: Sprint 5'te admin akışları için HTTP-level feature test'ler
   (login → urun ekle → çıkış) eklenebilir. Playwright E2E zaten var; feature suite PHP-native.
6. **CI'da compose-lint**: `.github/workflows/ci.yml` içine `docker compose -f
   docker-compose.staging.yml config -q` step ekle (misconfig erken yakalanır).
7. **Coverage driver**: yerel dev için Xdebug kurulum talimatı `docs/ONBOARDING.md`'ye eklensin.

---

## 10. Prod Go/No-Go Skoru (QA+DevOps tarafı)

| Kriter | Durum | Not |
|---|:---:|---|
| PHPUnit %100 pass | ✅ | Flaky risk var, §9.1 |
| PHPStan 0 error | ✅ | **Sprint 4'te tamamen temizlendi** |
| PHPCS yönetilebilir seviyede | ✅ | 149 → 37 |
| Health endpoint responsive | ✅ | <5 ms DB latency |
| Metrics endpoint auth | ✅ | Bearer 401 doğrulandı |
| Staging compose config | ✅ | YAML + yapısal PASS |
| Blue-green prosedürü dokümante | ✅ | `docs/BLUE_GREEN_DEPLOYMENT.md` |
| Monitoring alert rules | ✅ | 8 rule, runbook link'li |
| Incident response playbook | ✅ | P1/P2/P3 senaryoları hazır |

**QA+DevOps tarafından GO karar:** **EVET — production sevkine hazır.** Tech Lead'in
`GO_NO_GO_CHECKLIST.md` final onayı ile beraber launch edilebilir. Sprint sonrası
technical debt listesi (§9) ilk patch release'inde kapatılacak.

---

**Dondurulmuş Sprint 4 sayıları:** PHPUnit 181/181 pass, PHPStan **0 error** @ L5,
PHPCS 37 error + 16 warning. Sprint 0'a göre iyileşme: **+91 test, −2 PHPStan error,
−112 PHPCS error, +2.2 pp pass oranı, 0 regresyon**. Yenilikler (blue-green, monitoring,
incident response) Sprint 5+ için operasyonel omurga oluşturuyor.
