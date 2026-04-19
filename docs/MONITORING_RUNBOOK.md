# Monitoring Runbook — Pastane Production

**Belge sürümü:** 1.0 — Sprint 4 (2026-04-17)
**Sorumlu:** QA+DevOps
**İlgili:** `prometheus/alerts.yml`, `docs/INCIDENT_RESPONSE.md`, `api/metrics.php`, `api/health.php`

Bu doküman production monitoring stack'ini, alert rule katalogunu ve her alert için
"ne yap" talimatlarını içerir. On-call mühendislerin ilk başvuracağı tek kaynaktır.

---

## 1. Monitoring Stack

```
┌─────────────┐    scrape 15s    ┌─────────────┐    eval 30s    ┌──────────────┐
│  pastane    │ ───────────────> │ Prometheus  │ ─────────────> │ Alertmanager │
│ /api/metrics│                  │  (v2.51)    │                │              │
│ /api/health │                  │  TSDB 30d   │                │  PagerDuty/  │
└─────────────┘                  └──────┬──────┘                │  Slack/Email │
                                        │                       └──────────────┘
                                        │ query
                                        ▼
                                 ┌──────────────┐
                                 │   Grafana    │
                                 │  (dashboard) │
                                 └──────────────┘
```

**Kaynak endpoint'ler:**
- `GET /api/metrics.php` — Prometheus text format (Bearer `METRICS_TOKEN` auth)
- `GET /api/health.php` — JSON (public, 503 on down)
- `GET /api/health/live` — liveness (DB'ye bakmaz)
- `GET /api/health/ready` — readiness (DB+cache+disk)

**Scrape config:** `docker/prometheus-staging.yml` (staging referans); production için
`prometheus.yml` ayrıca oluşturulur, credentials_file mount edilir.

---

## 2. Grafana Dashboard — Panel JSON İskelet

`grafana-dashboards/pastane-overview.json` (bu sprint'te doküman olarak verilir, JSON
import'a hazır):

```json
{
  "title": "Pastane — Production Overview",
  "tags": ["pastane", "production"],
  "timezone": "browser",
  "refresh": "15s",
  "panels": [
    {
      "id": 1,
      "title": "Request rate (5m)",
      "type": "timeseries",
      "gridPos": {"x":0,"y":0,"w":12,"h":8},
      "targets": [{
        "expr": "sum by (status) (rate(pastane_http_requests_total[5m]))",
        "legendFormat": "{{status}}"
      }]
    },
    {
      "id": 2,
      "title": "HTTP 5xx ratio",
      "type": "stat",
      "gridPos": {"x":12,"y":0,"w":6,"h":8},
      "targets": [{
        "expr": "sum(rate(pastane_http_requests_total{status=~\"5..\"}[5m])) / sum(rate(pastane_http_requests_total[5m]))",
        "legendFormat": "5xx %"
      }],
      "fieldConfig": {
        "defaults": {
          "unit": "percentunit",
          "thresholds": {"steps": [{"color":"green","value":null},{"color":"yellow","value":0.01},{"color":"red","value":0.02}]}
        }
      }
    },
    {
      "id": 3,
      "title": "DB query p95 duration",
      "type": "timeseries",
      "gridPos": {"x":0,"y":8,"w":12,"h":8},
      "targets": [{
        "expr": "histogram_quantile(0.95, sum(rate(pastane_db_query_duration_seconds_bucket[5m])) by (le))",
        "legendFormat": "p95"
      }]
    },
    {
      "id": 4,
      "title": "Active sessions",
      "type": "stat",
      "gridPos": {"x":12,"y":8,"w":6,"h":8},
      "targets": [{"expr": "pastane_active_sessions"}]
    },
    {
      "id": 5,
      "title": "Cache hit ratio",
      "type": "stat",
      "gridPos": {"x":18,"y":0,"w":6,"h":8},
      "targets": [{
        "expr": "sum(rate(pastane_cache_operations_total{result=\"hit\"}[5m])) / sum(rate(pastane_cache_operations_total[5m]))",
        "legendFormat": "hit %"
      }],
      "fieldConfig": {"defaults": {"unit": "percentunit"}}
    },
    {
      "id": 6,
      "title": "PHP errors/sec",
      "type": "timeseries",
      "gridPos": {"x":18,"y":8,"w":6,"h":8},
      "targets": [{
        "expr": "sum by (level) (rate(pastane_php_errors_total[5m]))",
        "legendFormat": "{{level}}"
      }]
    },
    {
      "id": 7,
      "title": "Last backup age (hours)",
      "type": "stat",
      "gridPos": {"x":0,"y":16,"w":6,"h":6},
      "targets": [{
        "expr": "(time() - pastane_last_backup_timestamp) / 3600"
      }],
      "fieldConfig": {
        "defaults": {
          "unit": "h",
          "thresholds": {"steps": [{"color":"green","value":null},{"color":"yellow","value":25},{"color":"red","value":48}]}
        }
      }
    }
  ]
}
```

**Panel önerileri (eklenecek):**
- Disk free space (`pastane_disk_free_bytes`)
- Email sent/failed rate (`pastane_emails_sent_total`)
- Order throughput (`pastane_orders_total` by durum)
- Sentry event rate (eğer Sentry → Prometheus exporter varsa)

---

## 3. Alertmanager Kuralları

**Dosya:** `prometheus/alerts.yml` — 9 rule, 3 grup:

| # | Alert | Severity | Trigger | Duration |
|---|---|---|---|---:|
| 1 | `HealthDown` | critical | `up{job=~"pastane.*"} == 0` | 2m |
| 2 | `HighErrorRate` | critical | `rate(pastane_php_errors_total{level="error"}[5m]) > 10` | 5m |
| 3 | `Http5xxRatioHigh` | critical | `5xx/total > 0.02` | 5m |
| 4 | `DiskFull` | warning | `pastane_disk_free_bytes < 5 GiB` | 5m |
| 5 | `DbSlowQuery` | warning | `p95(db_query_duration) > 0.5s` | 10m |
| 6 | `BackupStale` | warning | `time() - last_backup > 86400` | 15m |
| 7 | `RateLimit429Spike` | warning | `rate(429)[5m] > 5` | 10m |
| 8 | `CacheDown` | warning | `cache errors > 0 && hits == 0` | 5m |
| 9 | `SentryEventSpike` | info | `increase(sentry_events[1h]) > 100` | 15m |

Her rule `labels.runbook` ile bu belgenin ilgili section'ına link'lidir.

---

## 4. Alert → Action Runbook

### 4.1. `HealthDown` (P1 / critical)

**Ne oldu?** Prometheus 2 dakikadır instance'ı scrape edemiyor. Health endpoint ölü.

**İlk 3 adım:**
1. `curl -sI https://api.tatlidusler.com/api/health/live` — DNS, TLS, temel bağlantı
2. `docker ps | grep pastane` — container up mı? (exit code, restart count)
3. `docker logs pastane-green --tail 200` (veya aktif slot) — exception / OOM

**Eğer çözüm bulunmadı (3 dk):**
- `bin/deploy.sh rollback` — blue-green flip
- PagerDuty'de incident aç, #incidents Slack kanalına link at

### 4.2. `HighErrorRate` (P1 / critical)

**Ne oldu?** PHP error_log seviyesinde > 10/5m hata.

**İlk 3 adım:**
1. Sentry dashboard: environment=production, level:error, last 15m → top issue
2. Son deploy'u kontrol: `git log --oneline -5` — son 30 dk'da push var mı?
3. Eğer yeni deploy ile korele: `bin/deploy.sh rollback`

### 4.3. `Http5xxRatioHigh` (P1 / critical)

**Ne oldu?** 5 dk'da 5xx/total > %2. Kullanıcı ciddi etkileniyor.

**İlk 3 adım:**
1. `docker logs pastane-green --tail 500 | grep -E "500|502|503"` — hangi endpoint?
2. DB durumu: `docker exec pastane-db mysqladmin ping` + `mysql> SHOW PROCESSLIST`
3. **Acil rollback tetikle**: `bin/deploy.sh rollback` + postmortem planla.

### 4.4. `DiskFull` (P2 / warning)

**Ne oldu?** Uygulama disk'i 5 GB altında.

**İlk 3 adım:**
1. `df -h` — hangi volume? Logs mu, backups mı, uploads mı?
2. `php bin/cron/log-rotate.php` — manuel rotate (retention 7 gün)
3. `ls -lahS storage/backups/ | head` — büyükten küçüğe, en eskileri sil:
   `find storage/backups -mtime +30 -delete`

### 4.5. `DbSlowQuery` (P2 / warning)

**Ne oldu?** DB query p95 > 500 ms (10 dk sürdü).

**İlk 3 adım:**
1. `SHOW FULL PROCESSLIST;` MySQL'de — uzun süren sorgu var mı?
2. MySQL slow query log: `tail -100 /var/log/mysql/slow.log | grep "Query_time"`
3. İlgili sorguya `EXPLAIN` + index öner. N+1 şüphesi varsa
   `docs/N1_AUDIT_SPRINT1.md` pattern'ini tekrarla.

### 4.6. `BackupStale` (P2 / warning)

**Ne oldu?** Son yedek 24 saatten eski.

**İlk 3 adım:**
1. `tail -30 storage/logs/db-backup.log` — en son ne zaman, ne hata?
2. Cron konfigürasyonu: Linux `crontab -l | grep db-backup`, Windows Task Scheduler
3. Manuel yedek al: `php bin/cron/db-backup.php`. Başarılı olursa cron sorunu izole et.

### 4.7. `RateLimit429Spike` (P2 / warning)

**Ne oldu?** 10 dk'dır sn başına > 5 adet 429 response dönüyor.

**İlk 3 adım:**
1. SecurityAudit log: `SELECT ip_address, COUNT(*) FROM security_events
   WHERE event_type='rate_limit_exceeded' AND created_at > NOW() - INTERVAL 15 MINUTE
   GROUP BY ip_address ORDER BY 2 DESC LIMIT 10;`
2. Top IP'ler dağınık mı, toplu mu? Toplu ise bot saldırısı — fail2ban/firewall
3. Config hatası şüphesi: `includes/RateLimiter.php` eşiklerini gözden geçir

### 4.8. `CacheDown` (P2 / warning)

**Ne oldu?** Cache hit=0, error>0 (Redis yanıt vermiyor).

**İlk 3 adım:**
1. `docker exec pastane-redis redis-cli ping` → PONG?
2. `docker logs pastane-redis --tail 100`
3. Redis restart: `docker compose restart redis`. App otomatik degrade eder
   (BaseService::cacheRemember graceful), ama DB yükü artar.

### 4.9. `SentryEventSpike` (P3 / info)

**Ne oldu?** Sentry'ye son 1 saatte > 100 event.

**İlk 3 adım:**
1. Sentry'de trend grafiğine bak — belirli bir issue yoğun mu?
2. Tekrarlayan aynı hata ise hotfix branch aç (`docs/INCIDENT_RESPONSE.md` P2 akışı)
3. Yeni exception class ise CLAUDE.md'ye ders notu ekle

---

## 5. Incident Severity Katmanları

| Sev | Tepki Süresi | Örnek | Aksiyon |
|-----|--------------|-------|---------|
| **P1** | < 15 dk | HealthDown, Http5xx > %2 | Sayfa oda, on-call, rollback düşün |
| **P2** | < 4 saat | DiskFull, DbSlowQuery, 429 spike | On-call bak, hotfix planla |
| **P3** | < 24 saat | Sentry spike, cache error | Mesai içinde çöz, ders notu al |
| **P4** | Sonraki sprint | PHPStan error, test flaky | Backlog'a al |

Her alert rule'ın `severity` label'ı Alertmanager'da routing için kullanılır:
- `critical` → PagerDuty (7/24)
- `warning` → Slack #pastane-alerts (mesai)
- `info` → Email (günlük özet)

---

## 6. On-Call Rotation

**Standart:** Haftalık rotasyon, Pazartesi 09:00 TRT başlar.

| Hafta | Primary | Secondary |
|-------|---------|-----------|
| W1 | Alice | Bob |
| W2 | Bob | Carol |
| W3 | Carol | Alice |

**Kurallar:**
- Primary P1 alert'e < 15 dk içinde acknowledge
- Primary ulaşılmazsa 15 dk sonra secondary'e otomatik eskalasyon (PagerDuty)
- Handoff: Pazartesi 10:00 — açık incident/warning'leri secondary'e brief et
- On-call haftasında deploy serbest, ancak **production hotfix'i secondary onaylar**
  (4 göz prensibi)
- Blue-green deploy sadece mesai içinde (09:00-17:00 TRT) — dışında sadece rollback

**İlk kurulum için öneri:** PagerDuty veya Opsgenie free tier, 3 kişi, email+SMS
integration. Slack webhook için `#pastane-alerts` kanalı.

---

## 7. Dashboard Erişim

- **Grafana:** https://grafana.tatlidusler.com (staging: :3000 direkt port)
- **Prometheus:** https://prom.tatlidusler.com (staging: :9090, profiles=observability)
- **Sentry:** https://sentry.io/organizations/tatlidusler/
- **Alertmanager:** https://alertmanager.tatlidusler.com

Her dashboard admin-panel SSO arkasında (Sprint 5+ hedefi). Şimdilik basic auth +
IP whitelist.

---

## 8. İlgili Belgeler

- `docs/INCIDENT_RESPONSE.md` — P1/P2/P3 playbook detayları
- `docs/BLUE_GREEN_DEPLOYMENT.md` — rollback komutu
- `docs/STAGING_SETUP.md` — monitoring'in staging'de kurulumu
- `prometheus/alerts.yml` — rule tanımları (tek kaynak doğruluğu)
- `api/metrics.php` header'ı — mevcut metriklerin listesi
- `includes/Metrics.php` — metrik kayıt API'si (kod tarafı)
