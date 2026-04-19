# Staging Environment Setup

Bu belge, pastane projesinin staging ortamını kurmak ve yönetmek için rehberdir.
Staging environment'ı production'a benzer bir ortamda test yapmayı, canary deploy
denemelerini ve Sprint kabul testlerini izole DB üstünde yürütmeyi amaçlar.

## 1. Genel Bakış

| Bileşen         | Port (staging)   | Port (prod)   | Not                                          |
|-----------------|------------------|---------------|----------------------------------------------|
| App (Apache)    | 8090             | 8080          | `STAGING_APP_PORT`                           |
| MySQL           | 33307            | 3306          | Localhost'tan debug için dış port            |
| Redis           | 6380             | 6379          |                                              |
| Prometheus      | 9090 (opsiyonel) | —             | `--profile observability` ile aktif         |

DB adı: `pastane_staging` (prod: `pastane`).
APP_ENV: `staging`, APP_DEBUG: `false`, Sentry sample rate: 0.5.

## 2. İlk Kurulum

### 2.1. `.env.staging` oluştur

```bash
cp .env.staging.example .env.staging
```

Değişkenleri doldur (parolalar + token'lar):

```bash
# DB
DB_PASSWORD=$(openssl rand -hex 24)
DB_ROOT_PASSWORD=$(openssl rand -hex 24)

# APP_KEY (base64 32 byte)
APP_KEY=base64:$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -hex 32)

# Observability token'ları — prod ile FARKLI olmalı
METRICS_TOKEN=$(openssl rand -hex 32)
HEALTH_TOKEN=$(openssl rand -hex 32)

# Sentry DSN — Sentry.io'da ayrı bir "staging" projesi aç, DSN'i buraya koy
SENTRY_DSN=https://PUBLIC_KEY@oXXXX.ingest.sentry.io/PROJECT_ID
```

`sed -i` ile otomatik de yapılabilir ama insan gözü değeri gördüğü için
tercihen `$EDITOR .env.staging` çalıştır.

### 2.2. Container stack'i başlat

```bash
docker compose --env-file .env.staging -f docker-compose.staging.yml up -d --build
```

Loglardan beklemeyi takip et:

```bash
docker compose -f docker-compose.staging.yml logs -f app
```

Sağlık kontrolü container'ın hazır olduğunu gösterir:

```bash
docker compose -f docker-compose.staging.yml ps
# STATUS: healthy olmalı
```

### 2.3. DB şemasını yükle

İki yol var:

**Yol A — `database.sql` init ile (otomatik)**
`docker/docker-entrypoint-initdb.d/` MySQL container'ı ilk başlatıldığında
`database.sql` ve `database/init/*.sql` dosyalarını otomatik uygular. Volume
temiz ise bu yeterli.

**Yol B — Migration script**
Zaten bir volume varsa ve manuel güncelleme gerekiyorsa:

```bash
docker compose -f docker-compose.staging.yml exec app php bin/migrate run
```

`bin/migrate` dosyası mevcut (bkz. `bin/migrate`). Tek tek migration dosyaları
`database/migrations/*.php` altında.

### 2.4. Seed data

Repo'da şu anda `bin/seed.php` **yok** (2026-04-17 itibarıyla). Staging'de
gerçekçi test verisi için iki seçenek:

**Seçenek 1 — Prod snapshot (anonimleştirilmiş):**
```bash
# Prod'da backup al (PII temizle)
mysqldump --single-transaction pastane | \
  sed -E 's/(email|telefon)="[^"]+"/\1="redacted"/g' > /tmp/staging-seed.sql

# Staging'e yükle
docker compose -f docker-compose.staging.yml exec -T db \
  mysql -u root -p${DB_ROOT_PASSWORD} pastane_staging < /tmp/staging-seed.sql
```

**Seçenek 2 — Manual fixture:**
`database.sql` varsayılan admin kullanıcısı + birkaç demo ürün içeriyor.
İlk kurulumda yeterli. Daha zengin seed gerekiyorsa `database/seeds/` altında
SQL dosyaları bırak, `bin/migrate run --seed` komutuyla tetikle (seed
infrastructure mevcut değilse önce eklenmeli — Sprint 2+ görevi).

### 2.5. DNS / Hosts

Geliştirme makinesinde:

```bash
# Linux/macOS → /etc/hosts
# Windows      → C:\Windows\System32\drivers\etc\hosts
127.0.0.1 staging.tatlidusler.local
```

Tarayıcıdan `http://staging.tatlidusler.local:8090` erişilebilir olmalı.

## 3. Smoke Test

Stack'in sağlıklı olduğunu doğrula:

```bash
# Health check (public)
curl -f http://localhost:8090/api/health/live
# {"status":"ok",...}

# Full health
curl -f http://localhost:8090/api/health | jq .

# Metrics (token ile)
curl -H "Authorization: Bearer $(grep METRICS_TOKEN .env.staging | cut -d= -f2)" \
     http://localhost:8090/api/metrics.php | head -20
```

## 4. Temizlik

Tüm staging verisini silmek için:

```bash
docker compose -f docker-compose.staging.yml down -v
```

`-v` flag'i volume'ları da siler — **DB + upload + cache kaybolur**.

## 5. GitHub Actions Staging Deploy

`main` branch'e merge olan her commit otomatik olarak staging'e push'lanır
(bkz. `.github/workflows/ci.yml` → `deploy-staging` job).

Gereken secret'lar (repository settings → Secrets):
- `STAGING_HOST` — staging sunucu FQDN/IP
- `STAGING_USER` — SSH kullanıcısı
- `STAGING_KEY` — deploy key (private)
- `DOCKER_USERNAME`, `DOCKER_PASSWORD` — registry kimlikleri

Deploy akışı:
1. `lint` + `test` + `build-frontend` başarıyla geçer.
2. `docker` job prod image'ı üretir ve registry'ye push'lar.
3. `deploy-staging` job staging sunucuya SSH'lar, `docker-compose.staging.yml`
   ile pull + up yapar.
4. Health check döner, job başarı ile biter.

Prod deploy hala manuel (environment protection ile) — bkz. `deploy` job.

## 6. Gözlemlenebilirlik

### Sentry

`.env.staging`'de `SENTRY_DSN` dolu olmalı. Sentry UI'da
`environment: staging` filtresi ile hataları izle.

### Prometheus (opsiyonel)

```bash
# Observability profile'ı ile başlat
docker compose --env-file .env.staging \
  -f docker-compose.staging.yml --profile observability up -d

# Prometheus UI
open http://localhost:9090
```

Prometheus konfigürasyonu: `docker/prometheus-staging.yml`. Bearer token
için `metrics_token` dosyası container'a mount edilmeli — otomasyon için
staging deploy script'inde:

```bash
echo "$METRICS_TOKEN" > /etc/prometheus/metrics_token
chmod 600 /etc/prometheus/metrics_token
```

### Sağlık izleme

Cron jobs (staging-cron container eklemek yerine host makine):
```
*/5 * * * * curl -sf http://staging.tatlidusler.local:8090/api/health/live || \
  echo "staging DOWN" | mail -s "Staging alert" oncall@example.com
```

## 7. Yaygın Sorunlar

| Sorun                                       | Çözüm                                             |
|---------------------------------------------|---------------------------------------------------|
| `bind: address already in use`              | `STAGING_APP_PORT` değiştir (`.env.staging`)      |
| MySQL başlamıyor, "access denied"           | `docker compose down -v` + password'ları yenile   |
| /api/metrics 503                            | `.env.staging`'de `METRICS_TOKEN` boş            |
| Sentry'ye event gitmiyor                    | DSN doğru mu, environment=staging mı?             |
| Prometheus target DOWN                       | `metrics_token` dosyası mount edildi mi?          |

## 8. Referans

- `docker-compose.staging.yml`
- `.env.staging.example`
- `docker/prometheus-staging.yml`
- `.github/workflows/ci.yml` → `deploy-staging`
- `docs/SECRET_ROTATION_RUNBOOK.md` (parola rotate prosedürü)
