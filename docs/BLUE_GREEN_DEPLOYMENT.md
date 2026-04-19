# Blue-Green Deployment Prosedürü

**Belge sürümü:** 1.0 — Sprint 4 (2026-04-17)
**Sorumlu:** QA+DevOps
**İlgili:** `bin/deploy.sh`, `docker-compose.blue.yml`, `docker-compose.green.yml`, `docs/INCIDENT_RESPONSE.md`

Bu doküman `pastane` servisinin **zero-downtime** production deploy prosedürünü anlatır.
Amaç: yeni sürümü **< 30 sn içinde rollback** edilebilir şekilde canary trafiğe sokmak.

---

## 1. Mimari Diyagramı

```
                           ┌─────────────────────────┐
                           │   Public DNS / CDN      │
                           │   api.tatlidusler.com   │
                           └───────────┬─────────────┘
                                       │ HTTPS
                                       ▼
                        ┌──────────────────────────────┐
                        │   Nginx Load Balancer        │
                        │   (TLS terminate, canary)    │
                        │   /etc/nginx/upstreams.conf  │
                        └───────────┬──────────────────┘
                                    │
                        ┌───────────┴───────────┐
                        │                       │
                        ▼                       ▼
                ┌───────────────┐       ┌───────────────┐
                │ pastane-blue  │       │ pastane-green │
                │ PHP-FPM+Apache│       │ PHP-FPM+Apache│
                │ port 8081     │       │ port 8082     │
                │ LIVE (100%)   │       │ IDLE (0%)     │
                └───────┬───────┘       └───────┬───────┘
                        │                       │
                        └──────────┬────────────┘
                                   ▼
                         ┌──────────────────┐
                         │     MySQL 8      │
                         │  (tek instance,  │
                         │   expand/        │
                         │   contract       │
                         │   migration)     │
                         └──────────────────┘
                                   │
                                   ▼
                         ┌──────────────────┐
                         │ Redis + storage/ │
                         │ shared volume    │
                         └──────────────────┘
```

### Kritik notlar
- **Tek MySQL instance**: blue/green aynı DB'yi paylaşır → migration `expand/contract`
  pattern zorunlu (aşağıda §3).
- **Redis + uploads volume**: ortak, session/cache/upload bölünmez.
- **Nginx LB**: tek upstream dosyası (`/etc/nginx/conf.d/pastane-upstream.conf`) canary
  weight'i belirler. `nginx -s reload` ile sıfır downtime geçiş.

---

## 2. Deploy Aşamaları (Özet)

| # | Aşama                             | Süre (tipik) | Geri dönüş (rollback) |
|---|-----------------------------------|-------------:|-----------------------|
| 1 | Green'e image pull                | 30-90 s      | —                     |
| 2 | Migration (expand, backward-compat) | 10-60 s    | `down` migration      |
| 3 | Green health check                | 10-20 s      | → S-0 (deploy iptal)  |
| 4a | Canary 10%                       | 5 dk         | Nginx flip (< 30 s)   |
| 4b | Canary 50%                       | 10 dk        | Nginx flip (< 30 s)   |
| 4c | Full 100% green                  | ∞            | Nginx flip (< 30 s)   |
| 5 | Blue hot-standby (1 saat)         | 60 dk        | —                     |
| 6 | Blue image refresh (next deploy'a hazırla) | 30-60 s | —            |

---

## 3. Migration: Expand/Contract Pattern

Tek DB'yi iki sürüm paylaştığı için **kırılgan kolon silme/rename işlemi YASAK**. Yeni
kolonlar eklenir, eskisi bir sonraki release'e kadar DB'de kalır.

### Release N (expand)
```sql
-- Yeni kolonu ekle (eskisi kalır)
ALTER TABLE siparisler ADD COLUMN musteri_adi_v2 VARCHAR(255) NULL;

-- Yeni kodda: hem yeni hem eski kolona yaz (dual-write)
UPDATE siparisler SET musteri_adi = ?, musteri_adi_v2 = ? WHERE id = ?;

-- Yeni kodda: oku — yeni kolon boşsa eskiye fallback
SELECT COALESCE(musteri_adi_v2, musteri_adi) AS isim FROM siparisler;
```

### Release N+1 (contract)
```sql
-- Tüm satırları yeni kolona migrate et (backfill job)
UPDATE siparisler SET musteri_adi_v2 = musteri_adi WHERE musteri_adi_v2 IS NULL;

-- Eski kolonu oku → artık sadece yeni kolon
-- Kod tek kaynaktan okur: musteri_adi_v2

-- Release N+2: eski kolonu sil
ALTER TABLE siparisler DROP COLUMN musteri_adi;
```

**Kural:** Her deploy'da **en fazla 1 expand step**. Contract'ı acele etme — 2 release
geç olursa bile sakıncası yok (production'da DB yavaşlatacak 30+ eski kolon biriktirme).

**CLAUDE.md uyumlu:** `siparisler.musteri_adi` kolon adı DB kanon (tekrar eden hata #1).
Rename değil, yeni kolon ekle pattern'i bu uyumsuzluğu kaynağında çözer.

---

## 4. Nginx Upstream Config (Canary)

**Dosya:** `/etc/nginx/conf.d/pastane-upstream.conf`

### 4.1. Başlangıç (100% blue)
```nginx
upstream pastane_backend {
    # BLUE = aktif, GREEN = idle
    server pastane-blue:80  weight=100 max_fails=3 fail_timeout=10s;
    server pastane-green:80 weight=0   max_fails=3 fail_timeout=10s backup;

    # Sticky session (session cookie üzerinden)
    # keepalive: upstream bağlantı havuzu
    keepalive 32;
    keepalive_timeout 60s;
    keepalive_requests 1000;
}

server {
    listen 443 ssl http2;
    server_name api.tatlidusler.com;

    # TLS, headers, rate-limit — kısaltıldı

    location / {
        proxy_pass http://pastane_backend;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        proxy_connect_timeout 3s;
        proxy_read_timeout    30s;
        proxy_send_timeout    30s;

        # Hata durumunda otomatik backup'a düş
        proxy_next_upstream error timeout http_502 http_503 http_504;
        proxy_next_upstream_tries 2;
    }

    # /api/health.php için rate-limit bypass
    location = /api/health.php { proxy_pass http://pastane_backend; }
    location ~ ^/api/health/ { proxy_pass http://pastane_backend; }
}
```

### 4.2. Canary 10% green
```nginx
upstream pastane_backend {
    server pastane-blue:80  weight=90;
    server pastane-green:80 weight=10;
    keepalive 32;
}
```

### 4.3. Canary 50% green
```nginx
upstream pastane_backend {
    server pastane-blue:80  weight=50;
    server pastane-green:80 weight=50;
    keepalive 32;
}
```

### 4.4. Full 100% green
```nginx
upstream pastane_backend {
    server pastane-blue:80  weight=0 backup;
    server pastane-green:80 weight=100;
    keepalive 32;
}
```

**Reload komutu (sıfır downtime):**
```bash
sudo nginx -t && sudo nginx -s reload
```

`nginx -s reload` açık bağlantıları sonlanana kadar bekler, yeni config ile yeni worker
başlatır. Request kaybı yok.

---

## 5. Rollback: < 30 sn

**Senaryo:** Green canary'de error rate %5'i aştı (P2 alert).

```bash
# 1) Upstream'i baseline'a çevir (blue = 100%, green = 0%)
sudo cp /etc/nginx/conf.d/pastane-upstream.conf.baseline \
        /etc/nginx/conf.d/pastane-upstream.conf

# 2) Reload
sudo nginx -t && sudo nginx -s reload

# 3) Green container'ı durdur (log/inspect için bırakabilirsin de)
docker compose -f docker-compose.green.yml stop app

# 4) Monitoring doğrula: error rate 5 dk içinde normale dönmeli
curl -sfH "Authorization: Bearer $METRICS_TOKEN" \
     https://api.tatlidusler.com/api/metrics.php | grep pastane_php_errors
```

**Hedef:** Canary başladıktan sonra sorun tespit edilene kadar max 5 dk + flip 30 sn =
en kötü **5.5 dk etkilenen trafiği**.

### Rollback Tetikleyicileri (otomatik önerilir)
- HTTP 5xx oranı > %2 (son 5 dk)
- `pastane_php_errors_total{level="error"}` > 20/5 dk
- Health endpoint `status: down` (2 successive 503)
- DB slow query p95 > 500 ms
- Manuel: PagerDuty "rollback requested"

---

## 6. Docker Compose Varyantları

### 6.1. `docker-compose.blue.yml`
Blue slot için ayrı compose. Port 8081'de expose eder. DB/Redis'e `pastane-network`
üzerinden bağlanır (prod'da ortak).

### 6.2. `docker-compose.green.yml`
Green slot için ayrı compose. Port 8082'de expose eder. Aynı network.

Her iki slot da aynı MySQL/Redis container'ına bağlanır. İlk kurulumda `pastane-network`
dışarıdan yaratılır:

```bash
docker network create pastane-network
```

---

## 7. Deploy Script: `bin/deploy.sh`

Manuel veya CI-driven deploy için bash script. Kullanım:

```bash
# Green'e v1.2.0 deploy et (blue hâlâ aktif)
./bin/deploy.sh deploy green v1.2.0

# Trafik 10% green
./bin/deploy.sh canary 10

# Trafik 50% green
./bin/deploy.sh canary 50

# Full green
./bin/deploy.sh promote green

# ACİL: blue'ya geri dön
./bin/deploy.sh rollback

# Durum
./bin/deploy.sh status
```

Script detayı için `bin/deploy.sh` header'ına bakın — komut flag'leri, health check
retry, logging, lock dosyası (concurrent deploy önleme) içerir.

---

## 8. Pre-Deploy Checklist (GO kriter)

- [ ] Sprint regression raporu PASS (`tests/regression-report-sprint4-final.md`)
- [ ] `docker compose -f docker-compose.blue.yml config -q` (syntax)
- [ ] `docker compose -f docker-compose.green.yml config -q`
- [ ] DB backup alındı (`bin/cron/db-backup.php --retention=7`)
- [ ] Staging smoke test PASS (§4 of `regression-report-sprint4-final.md`)
- [ ] `.env.production` secrets doğrulandı (DB, REDIS, SENTRY_DSN, METRICS_TOKEN)
- [ ] Slack/PagerDuty deploy notification hazır
- [ ] On-call mühendis bekleme odasında (30 dk canary periyodu boyunca)
- [ ] Rollback command'i hazırda bekliyor (terminal'de yazılı)

---

## 9. Post-Deploy Doğrulama

Deploy tamamlandıktan sonra (full green):

```bash
# 1) Health check (public)
curl -s https://api.tatlidusler.com/api/health/live | jq .
# beklenen: {"status":"ok",...}

curl -s https://api.tatlidusler.com/api/health/ready | jq .
# beklenen: {"status":"ok","checks":{...},...}

# 2) Metrics snapshot
curl -sH "Authorization: Bearer $METRICS_TOKEN" \
     https://api.tatlidusler.com/api/metrics.php > /tmp/metrics-$(date +%s).txt

# 3) Sentry son 5 dk error rate
# https://sentry.io/organizations/... filter: environment=production since=-5m

# 4) Basit akış smoke (gerçek kullanıcı simülasyonu)
curl -sfL https://tatlidusler.com/ > /dev/null          # anasayfa
curl -sfL https://tatlidusler.com/menu > /dev/null       # menü
curl -sfL https://tatlidusler.com/api/v1/urunler > /dev/null  # API

# 5) DB migration doğrulaması (admin panel'den)
# admin/ayarlar/backup → "son backup zamanı" güncel
# admin/dashboard → bugünkü sipariş sayısı doğru
```

**Karar noktası:** Yukarıdaki 5 kontrol PASS ise blue'yu 1 saat sıcak tut, sonra sonraki
deploy için image refresh et (§10).

---

## 10. Blue Image Refresh (Next Deploy Hazırlık)

Green stable olduktan 1 saat sonra:

```bash
# Blue'ya aynı imajı pull et (next deploy'da green → blue rol değişecek)
docker compose -f docker-compose.blue.yml pull
docker compose -f docker-compose.blue.yml up -d --no-deps app

# Healthcheck
./bin/deploy.sh health blue
```

Artık her iki slot aynı sürümde. Bir sonraki deploy'da rol ters döner:
**blue → stage, green → live**. Bu "rol dönüş" disiplini rollback süresini her zaman
< 30 sn tutar.

---

## 11. Bilinen Sınırlamalar

1. **Session affinity**: Sticky session yok — kullanıcı blue'dan green'e geçerken
   oturum Redis'te ortak olduğu için sorun yok. Redis outage olursa sticky cookie
   lazım (Sprint 5+).
2. **Long-running request**: Canary flip sırasında 30+ sn süren request'ler (örn.
   büyük rapor export) kopabilir. `proxy_read_timeout 30s` yeterli, ama CSV export
   için ayrı path + longer timeout önerilir.
3. **Schema breaking change**: `DROP COLUMN` / `RENAME COLUMN` **tek deploy'da yasak**.
   Expand/contract (§3) zorunlu. CI'da migration linter (`*.php` içinde `DROP COLUMN`
   tarama) eklenebilir (Sprint 5+).
4. **Cache invalidation**: Ortak Redis → yeni kod eski cache key'i okursa bozuk veri.
   Çözüm: her deploy'da `Cache::flushByPrefix("v1.2.0:")` değil, `CACHE_VERSION_PREFIX`
   env var'ı ile key rotation.

---

## 12. İlgili Belgeler

- `docs/INCIDENT_RESPONSE.md` — P1/P2/P3 playbook'u (rollback dahil)
- `docs/MONITORING_RUNBOOK.md` — alert → action eşleşmeleri
- `docs/STAGING_SETUP.md` — staging env kurulum
- `tests/regression-report-sprint4-final.md` — go/no-go sayıları
- `bin/deploy.sh` — otomasyon script
