# Incident Response Playbook — Pastane Production

**Belge sürümü:** 1.0 — Sprint 4 (2026-04-17)
**Sorumlu:** QA+DevOps
**İlgili:** `docs/MONITORING_RUNBOOK.md`, `docs/BLUE_GREEN_DEPLOYMENT.md`, `bin/deploy.sh`

Bu playbook production incident'ları için severity-bazlı adım adım yanıt prosedürüdür.
On-call mühendis PagerDuty bildirimi aldığında **önce bu dokümana bakar**.

---

## 0. Genel Akış (Tüm Severity'ler)

```
  ALERT geldi
       │
       ▼
  ┌──────────────────┐
  │ Acknowledge <15m │ → PagerDuty ACK
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ Slack kanal aç   │ → #inc-YYYYMMDD-<slug>
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ Severity belirle │ → P1 / P2 / P3
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ Playbook uygula  │ → §1 / §2 / §3
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ Postmortem       │ → P1 zorunlu, P2 önerilen
  └──────────────────┘
```

**Altın kural:** Önce **mitigate** (etkiyi sınırla), sonra **root cause analyze**
(kök neden). Kullanıcıyı etkileyen bir sorunda "neden oldu"yu incelemeden önce
"akışı durdur"u hedefle.

---

## 1. P1 Playbook — Pastane Down / Critical Outage

**Tetikleyiciler:**
- `HealthDown` (health endpoint 2+ dk yanıtsız)
- `Http5xxRatioHigh` (5xx ratio > %2)
- `HighErrorRate` (PHP error > 10/5m)
- Kullanıcı raporu: "site çalışmıyor"

**Hedef tepki süresi:** < 15 dk (ack) + < 30 dk (mitigate)

### 1.1. Adım-Adım Tepki

#### Dakika 0-5: Bağlantı & Triage
```bash
# 1) Kullanıcı tarafını doğrula
curl -sI https://tatlidusler.com/ | head -3
curl -sI https://api.tatlidusler.com/api/health/live

# 2) Hangi slot aktif?
bin/deploy.sh status | grep -E "(Upstream|Blue Health|Green Health)"

# 3) Container durumu
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# 4) Son deploy
git log --oneline -5
```

#### Dakika 5-10: Rollback Kararı
**Eğer son 30 dk'da deploy yapıldıysa → ROLLBACK:**

```bash
# ACİL: blue-green flip
bin/deploy.sh rollback

# Doğrula
sleep 15
curl -sf https://api.tatlidusler.com/api/health/live | jq .status
# beklenen: "ok"
```

**Eğer deploy bağlantılı değil → Container restart:**
```bash
docker compose -f docker-compose.green.yml restart app
# veya
docker compose -f docker-compose.blue.yml restart app
```

#### Dakika 10-15: Validation
```bash
# Rollback sonrası smoke
curl -sfL https://tatlidusler.com/ > /dev/null && echo HOMEPAGE_OK
curl -sfL https://tatlidusler.com/api/v1/urunler > /dev/null && echo API_OK
curl -sf https://tatlidusler.com/api/health/ready | jq .status

# Metrikleri izle (5 dk)
watch -n 10 'curl -sH "Authorization: Bearer $METRICS_TOKEN" https://api.tatlidusler.com/api/metrics.php | grep pastane_http_requests_total'
```

#### Dakika 15+: Root Cause & Postmortem
1. Sentry'de incident süresince gelen event'leri topla, top issue'yu belirle
2. `git log <broken_tag>..<rolled_back_tag> --oneline` ile suçlu commit'i ara
3. Hotfix branch aç: `git checkout -b hotfix/incident-YYYYMMDD-<slug>`
4. Fix'i yaz, testlerini çalıştır, PR → merge → staging'de doğrula
5. Yeniden deploy: `bin/deploy.sh deploy green v1.2.1` (yeni canary döngüsü)

### 1.2. Postmortem Şablonu
`docs/postmortems/YYYYMMDD-<slug>.md` altına yaz (24 saat içinde):

```markdown
# Postmortem: <başlık>
- Date: YYYY-MM-DD  Duration: HH:MM
- Severity: P1  Impact: <ör. site 12 dk ulaşılamadı, ~200 kullanıcı>
- Detect time: <dk> (alert → ack)
- Mitigate time: <dk> (alert → resolved)

## Timeline
- HH:MM: alert fired
- HH:MM: on-call acknowledged
- HH:MM: rollback triggered
- HH:MM: verified recovery

## Root Cause
<1-2 paragraf teknik sebep>

## What Went Well
- ...

## What Went Poorly
- ...

## Action Items
- [ ] #1 (owner, due)
- [ ] #2 ...
```

---

## 2. P2 Playbook — Error Spike / Degradation

**Tetikleyiciler:**
- Sentry'de aynı exception 10+ kez son 30 dk
- Yeni `TypeError` / `PDOException` yaygın
- `RateLimit429Spike`, `DbSlowQuery`, `CacheDown`, `DiskFull`
- Kullanıcı raporu: "bazı sayfalar yavaş/bozuk"

**Hedef tepki süresi:** < 4 saat

### 2.1. Sentry Analiz Akışı

```
Sentry dashboard
  ├─ Environment: production
  ├─ Level: error
  ├─ Time range: last 6h
  └─ Sort: events (desc)
     ▼
  Top issue seç
     ├─ Stack trace incele
     ├─ Breadcrumbs (son 20 event)
     ├─ Request context (URL, user-agent, user_id)
     └─ Release tag: hangi deploy'da başladı?
```

### 2.2. Hotfix Branch Akışı

```bash
# 1) Main'den branch
git checkout main
git pull
git checkout -b hotfix/<issue-id>-<short-desc>

# 2) Fix + test
# ... kod değişikliği ...
vendor/bin/phpunit --filter <AffectedTest>
vendor/bin/phpstan analyse --no-progress

# 3) Commit + push
git commit -m "fix: <specific description>

Refs: SENTRY-<id>
Incident: P2 at YYYY-MM-DD HH:MM"
git push -u origin hotfix/<issue-id>-<short-desc>

# 4) PR → staging test → main merge
# gh pr create --base main --title "hotfix: ..." --body "..."

# 5) Deploy
bin/deploy.sh deploy green v1.2.N+1
bin/deploy.sh canary 10    # gözlem 5 dk
bin/deploy.sh canary 50    # gözlem 10 dk
bin/deploy.sh promote green

# 6) Sentry'de issue'yu resolve et + release tag'i işaretle
```

### 2.3. P2 Özel Durumlar

#### 429 Spike
- SecurityAudit log: IP pattern
- Uzayan tek IP varsa: firewall/fail2ban block
- Dağınık IP (bot net) ise: CloudFlare rate limit sertleştir

#### CacheDown
- Redis restart → BaseService graceful degrade zaten var
- DB yükü izle (geçici yüksek olabilir)
- Cache warm-up: en sık okunan anahtarları preload

#### DiskFull
- Manuel rotate + eski backup sil (bkz. MONITORING_RUNBOOK §4.4)
- Kök neden: hangi klasör şişti? Cron ekle veya retention kısalt

---

## 3. P3 Playbook — Slow Query / Performance

**Tetikleyiciler:**
- `DbSlowQuery` (p95 > 500 ms)
- `SentryEventSpike` (event rate arttı ama error değil)
- Kullanıcı raporu: "admin panel yavaş"

**Hedef tepki süresi:** < 24 saat (mesai içi)

### 3.1. MySQL Slow Query Analiz

```bash
# 1) Slow log aktif mi?
docker exec pastane-db mysql -uroot -p$DB_ROOT_PASSWORD -e "SHOW VARIABLES LIKE 'slow_query%';"
docker exec pastane-db mysql -uroot -p$DB_ROOT_PASSWORD -e "SHOW VARIABLES LIKE 'long_query_time';"

# 2) Geçici etkinleştir (production care)
docker exec pastane-db mysql -uroot -p$DB_ROOT_PASSWORD \
  -e "SET GLOBAL slow_query_log = 'ON'; SET GLOBAL long_query_time = 0.3;"

# 3) 15 dk bekle, log'u analiz et
docker exec pastane-db tail -500 /var/lib/mysql/slow.log > /tmp/slow.log
pt-query-digest /tmp/slow.log | head -80   # eğer percona-toolkit varsa

# Veya manuel:
grep -B2 "Query_time" /tmp/slow.log | head -100
```

### 3.2. EXPLAIN Akışı

```sql
-- Suçlu sorgu (örn. admin/raporlar.php)
EXPLAIN ANALYZE
SELECT ... FROM siparisler
JOIN siparis_kalemleri ON ...
WHERE tarih BETWEEN ? AND ?
ORDER BY toplam_tutar DESC
LIMIT 50;

-- Bak: 'rows examined' > 100k → eksik index
-- Bak: 'Using filesort' → ORDER BY kolonu index'de değil
-- Bak: 'Using temporary' → GROUP BY + JOIN karışık
```

**Index ekleme migration şablonu:**
```sql
-- database/migrations/YYYY_MM_DD_add_index_<table>_<col>.php
ALTER TABLE siparisler ADD INDEX idx_tarih_tutar (tarih, toplam_tutar);
```

Migration'ı staging'de ölç (öncesi/sonrası `EXPLAIN`), production'a expand pattern
ile uygula (index ekleme breaking değil, direkt deploy edilebilir).

### 3.3. N+1 Şüphesi

CLAUDE.md Sprint 1 lesson: `docs/N1_AUDIT_SPRINT1.md` pattern'ini tekrarla.

```php
// KÖTÜ (N+1)
foreach ($siparisler as $s) {
    $s['kalemler'] = $repo->getKalemler($s['id']);
}

// İYİ (1 toplu query)
$ids = array_column($siparisler, 'id');
$kalemlerByIds = $repo->getKalemlerBySiparisIds($ids);
foreach ($siparisler as &$s) {
    $s['kalemler'] = $kalemlerByIds[$s['id']] ?? [];
}
```

---

## 4. P4 — Cosmetic / Tech Debt

**Tetikleyiciler:**
- PHPStan error
- PHPCS warning
- Flaky test
- Log spam (repetitive warning)

**Hedef:** Sonraki sprint backlog'una gir. Immediate aksiyon yok, ders notu alınır.

---

## 5. Eskalasyon Ladder

```
P1:
  0 dk   → PagerDuty primary
  15 dk  → PagerDuty secondary (ack yoksa)
  30 dk  → Tech Lead
  60 dk  → Engineering Manager + CEO

P2:
  0 dk   → Slack #pastane-alerts
  2 sa   → Primary on-call DM
  4 sa   → Tech Lead

P3:
  0 dk   → Slack #pastane-dev (mesai)
  8 sa   → Ticket sistemine git (Jira/Linear)
```

---

## 6. Post-Incident Review (P1 zorunlu)

**48 saat içinde** async postmortem, **1 hafta içinde** live review toplantısı.

### Review Toplantısı Gündem (30 dk)
1. Timeline review (5 dk)
2. Root cause (10 dk)
3. What went well / poorly (5 dk)
4. Action items (10 dk) — sahibi + due date

### Action Item Takibi
- Her action item GitHub issue'ya çevrilir (`label: incident-ai`)
- Tech Lead haftalık backlog review'da ilerlemeyi sorar
- 30 gün geçen AI'lar kapatılmazsa eskalasyon

---

## 7. Komut Referans Kartı

```bash
# Acil Rollback (< 30 sn)
bin/deploy.sh rollback

# Durum özeti
bin/deploy.sh status

# Health snapshot
curl -s https://api.tatlidusler.com/api/health.php | jq .

# Metrics snapshot
curl -sH "Authorization: Bearer $METRICS_TOKEN" \
     https://api.tatlidusler.com/api/metrics.php > /tmp/metrics-$(date +%s).txt

# Son hatalar (auth required)
curl -sH "Authorization: Bearer $HEALTH_TOKEN" \
     https://api.tatlidusler.com/api/health/errors | jq .

# Container log tail
docker logs pastane-green --tail 200 -f

# DB processlist
docker exec pastane-db mysql -uroot -p$DB_ROOT_PASSWORD \
  -e "SHOW FULL PROCESSLIST;" | grep -v Sleep

# Cache flush (dikkat!)
docker exec pastane-redis redis-cli FLUSHDB
```

---

## 8. İlgili Belgeler

- `docs/MONITORING_RUNBOOK.md` — alert → runbook eşleşmeleri
- `docs/BLUE_GREEN_DEPLOYMENT.md` — rollback detayları
- `prometheus/alerts.yml` — alert rule tanımları
- `docs/SECURITY_AUDIT_SPRINT3.md` — güvenlik incident'larında referans
- `docs/SECRET_ROTATION_RUNBOOK.md` — DB şifre rotate incident'ı
- `docs/postmortems/` — geçmiş incident analizleri
