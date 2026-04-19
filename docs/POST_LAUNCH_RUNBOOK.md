# Post-Launch Runbook — İlk 24 Saat & Sonrası

**Versiyon:** 1.0
**Kapsam:** Production launch sonrası izleme, hotfix ve retrospektif prosedürleri.
**Sahibi:** Tech Lead + On-call Developer
**Referans:** `docs/DEPLOYMENT_PLAN.md`, `docs/GO_NO_GO_CHECKLIST.md`

---

## 1. İzleme Timeline

### 1.1 İlk 15 Dakika (T+0 → T+15 dk) — Aktif Gözlem

**Kim:** Tech Lead + DevOps + On-call Developer (3 kişi ekranda).

**Ne yapılır:**
- **Tail log'ları** (3 pencere):
  - `tail -f storage/logs/app-$(date +%Y-%m-%d).log`
  - `tail -f storage/logs/error-$(date +%Y-%m-%d).log`
  - `docker compose logs -f --tail=50 app`
- **Manuel smoke test her 5 dk:**
  - Ana sayfa → 200
  - Admin login → session OK
  - Sipariş oluştur → 201, DB'de kayıt
  - `/api/health/ready` → 200
- **Sentry issue feed**: Yeni issue ≤ 2 (ignorable). Üstüne çıkarsa **DURU**.
- **Grafana "Pastane Prod"**: requests/sec, error_rate, p95 latency grafikleri.

**Red flag (anında rollback kararı):**
- Error rate > %2 sürekli 2 dk.
- `/api/health/ready` 5xx > 1 dk.
- DB bağlantı hatası.
- Sentry'de 10+ yeni issue / 1 dk.

### 1.2 İlk 1 Saat (T+15 dk → T+1h) — Yarı-Aktif

**Kim:** On-call Developer + Tech Lead (Tech Lead 45. dk sonra aralıklı check).

**Metric watch:**
| Metric | Sağlık eşiği | Alert eşiği |
|--------|--------------|-------------|
| `pastane_http_requests_total` (5m rate) | Staging baseline'ın ±%30'u | %50+ sapma |
| Error rate (5xx / total) | < %0.5 | > %1 (ALERT) |
| p95 request latency | < 200ms | > 400ms (ALERT) |
| DB p95 query latency | < 100ms | > 300ms (ALERT) |
| `pastane_cache_operations_total{result="hit"}` oranı | > %70 | < %40 (WARN) |
| Sentry new issues | ≤ 5 / saat | > 10 / saat (ALERT) |
| `ordered_count` (sipariş akış) | Non-zero (trafik varsa) | 15 dk 0 (ALERT) |

**Görevler:**
- Sentry'de her yeni issue incelenir: ignore / triage / hotfix.
- Grafana dashboard her 15 dk'da anomali gözden geçir.
- Payment sandbox → bir gerçek test siparişi (1 kuruş). OK ise T+30'da test siparişini iade et.
- Admin kullanıcılardan 3 kişi manuel akış testi yapar (sipariş listeleme, rapor görüntüleme, 2FA enable).

### 1.3 İlk 24 Saat (T+1h → T+24h) — Periyodik

**Kim:** On-call Developer (aktif), Tech Lead (gündüz saatlerinde check).

**Periyodik kontrol (her 2 saat):**
- [ ] `/api/health/ready` → 200
- [ ] Grafana dashboard açık/kapat
- [ ] Sentry issue list
- [ ] Disk free `df -h` → > 10GB
- [ ] DB connection pool `SHOW STATUS LIKE 'Threads_connected'`

**Gece saatlerinde (TR 00:00–08:00):**
- Alert-only mode. On-call PagerDuty üzerinden uyandırılır.
- 3 dk içinde ack; 15 dk içinde ilk diagnosis.

**24h özet raporu (T+24h):**
- Toplam request sayısı
- Peak rps, p95 latency min/max
- Error count + Sentry issue list + çözüm durumları
- Hotfix sayısı ve detayları
- Yeni öğrenilen dersler → CLAUDE.md

---

## 2. Kritik Metrik Alert Eşikleri

### 2.1 Anında Alert (Slack + PagerDuty)

| Metric | Threshold | Aksiyon |
|--------|-----------|---------|
| `pastane_php_errors_total` | > 10 / dk (5 dk ortalama) | On-call hemen müdahale |
| `/api/health/ready` | HTTP 5xx 1 dk sürekli | Incident aç, rollback değerlendir |
| `/api/health/live` | HTTP 5xx 30 sn sürekli | Kritik — rollback başlat |
| Disk free | < 5GB | Log rotate zorla + disk büyüt |
| DB p95 latency | > 500ms 5 dk ortalama | DB process list incele, slow query |
| DB connection count | > max_connections × %85 | Connection pool incele |
| Redis memory | > maxmemory × %90 | Eviction policy kontrol, scale |
| 502/503/504 oranı | > %2 / 5 dk | Upstream/proxy sorun |

### 2.2 Uyarı (Slack #ops, insan müdahalesi gündüz)

| Metric | Threshold | Aksiyon |
|--------|-----------|---------|
| Error rate | > %0.5 / 15 dk | Triage |
| p95 request latency | > 300ms / 15 dk | Perf analiz |
| Cache hit rate | < %40 / 30 dk | TTL ve invalidation gözden geçir |
| Backup fail | 25h içinde backup yok | Backup job log |
| Sentry issue burst | > 5 / saat | Triage + hotfix adayı |
| Failed login burst | > 100 / dk (tek IP) | Rate limit + IP block |

### 2.3 Bilgi (Slack #ops, insan müdahalesi haftalık)

- 429 rate-limit response oranı
- Admin activity log'da anormal pattern
- 2FA enable oranı trendi

---

## 3. Alert Routing

```
  [Prometheus Alert Rule]
           |
           v
     [Alertmanager]
     /      |      \
    /       |       \
 Slack  PagerDuty  Email
#alerts  (P0/P1)  (haftalık rapor)
```

**Ciddiyet eşleme:**
- **P0** (Anında): PagerDuty 24/7, on-call uyandırılır. Örn: prod down.
- **P1** (1 saat): PagerDuty gündüz, Slack gece. Örn: error rate > %1.
- **P2** (4 saat): Slack #alerts. Örn: cache hit rate düştü.
- **P3** (Günlük review): Slack #ops. Örn: disk %70 doldu.

---

## 4. Hotfix Prosedürü

### 4.1 Tanım
**Hotfix** = production'da görülen kritik bug'a karşılık, normal release cycle'ı beklemeden yapılan minimum-impact fix.

### 4.2 Kim Onaylar?
- **P0 / P1 hotfix**: Tech Lead **TEK BAŞINA** yetkili (gece dahil). Post-hoc Product Owner bilgilendirilir.
- **P2 hotfix**: Tech Lead + Product Owner onayı (gündüz saati).
- **P3+**: Normal sprint sürecine dahil edilir.

### 4.3 Süre Hedefleri
| Ciddiyet | İlk response | Patch canlı | Post-mortem |
|----------|--------------|-------------|-------------|
| P0 | 5 dk | 1 saat | 48 saat içinde |
| P1 | 15 dk | 4 saat | 72 saat içinde |
| P2 | 1 saat | 1 gün | Haftalık retro |

### 4.4 Hotfix Akışı
1. **Incident aç**: `docs/incidents/YYYY-MM-DD-HHMM-baslik.md` oluştur.
2. **Branch aç**: `hotfix/issue-N-aciklama` — `main`'den çıkar.
3. **Fix yaz**: minimum-impact, test dahil.
4. **CI geçir**: PHPUnit + PHPStan + PHPCS + e2e smoke.
5. **Review**: Tech Lead + 1 başka dev (P0'da solo-merge'e izin var, post-hoc review).
6. **Deploy**: Blue-Green canary (10% → 100% kademeli) veya direct rolling restart.
7. **Doğrula**: 15 dk izle, metric'ler normale döndü mü.
8. **Iletişim**: Slack #ops, status page update.
9. **Post-mortem**: 5 whys, CLAUDE.md dersleri.

### 4.5 Hotfix "Break glass" Senaryoları
Solo yetki:
- Güvenlik açığı aktif sömürü altındaysa (CISO 1 dk içinde onay).
- Data corruption riski varsa (Product Owner 5 dk onay).
- Payment sistemi çökmüşse (Tech Lead 5 dk onay).

---

## 5. Log ve Metric Erişimi

### 5.1 Log Dosyaları
| Dosya | İçerik | Rotate |
|-------|--------|--------|
| `storage/logs/app-YYYY-MM-DD.log` | Genel app log | Günlük, 30 gün tut |
| `storage/logs/error-YYYY-MM-DD.log` | ERROR ve üstü | Günlük, 60 gün tut |
| `storage/logs/audit-YYYY-MM-DD.log` | Admin işlemleri | Günlük, 365 gün tut |
| `storage/logs/cron-*.log` | Cron çıktıları | Haftalık |
| `storage/logs/sentry-transport.log` | Sentry send başarısızlıkları | 10MB rotate |

### 5.2 Metric Endpoint
- `GET /api/metrics` → Prometheus text format
- Auth: `Authorization: Bearer ${METRICS_TOKEN}`
- Scrape interval: 15s
- Prometheus retention: 30 gün (scale için)

### 5.3 Health Endpoint
- `GET /api/health/live` → PHP up (DB bakılmaz)
- `GET /api/health/ready` → DB + cache + disk (full readiness)
- `GET /api/health` → detaylı JSON (k8s probe için map'lenmiş)

---

## 6. Haftalık Retrospektif Şablonu

**Tarih:** ____
**Katılımcılar:** Tech Lead, Product Owner, QA, DevOps, 1 dev

### 6.1 Sayılar
- Uptime: %____
- P0/P1 incident sayısı: ___
- Ortalama response time p95: ____ ms
- En çok hata fırlatan endpoint: ____
- Backup/restore provası yapıldı mı: [ ] Evet [ ] Hayır
- Yeni 2FA kullanıcı: ____
- Toplam sipariş sayısı: ____
- Ort. sipariş başına latency: ____ ms

### 6.2 İyi Gidenler
- ...

### 6.3 Kötü Gidenler / Anomaliler
- ...

### 6.4 Aksiyon Maddeleri
- [ ] Sahip: ___ Son tarih: ___
- [ ] Sahip: ___ Son tarih: ___

### 6.5 CLAUDE.md Derslerine Eklenecek
- [2026-__-__] ...

---

## 7. Bilinen Riskler ve Azaltmalar

| Risk | Olasılık | Etki | Azaltma |
|------|----------|------|---------|
| Yüksek traffic peak (ilk gün) | Orta | Yüksek | Auto-scale config, cache ısıtma |
| DB deadlock (mutfak panel + sipariş) | Düşük | Orta | Retry with backoff, transaction time < 1s |
| Sentry send fail (internet flakey) | Düşük | Düşük | Local transport log + retry cron |
| Disk dolması (backup + log) | Orta | Orta | Log rotate cron + backup monitor |
| Payment gateway timeout | Orta | Yüksek | Idempotency key + 30s timeout + retry |
| 3rd party SMS outage | Düşük | Düşük | Fallback: email-only notify |
| Cache (Redis) down | Düşük | Orta | Graceful degrade (cacheRemember → direct DB) |

---

## 8. On-call Günlüğü

Her on-call shift sonunda kısa note (`docs/oncall/YYYY-MM-DD-name.md`):

```markdown
# On-Call: 2026-04-__ — İsim

## Shift detay
- Başlangıç: HH:MM
- Bitiş: HH:MM

## Uyandırıldım mı?
- [ ] Hayır
- [ ] Evet — detay:

## Müdahale edilen alert sayısı
- P0: 0
- P1: 0
- P2: 0
- P3: 0

## Aksiyonlar
- ...

## Devrettiğim açık konular
- ...
```

---

## 9. İletişim Kanalları

| Kanal | Kullanım |
|-------|----------|
| Slack #alerts | Anında tüm alert'ler |
| Slack #ops | Operational, günlük |
| Slack #support | Müşteri destek bilgilendirme |
| PagerDuty | P0/P1 gece gündüz |
| Email ops@pastane.tld | Haftalık rapor |
| Status page | Müşteri-yüzü anons |

---

## 10. Revizyon Geçmişi

| Tarih | Sürüm | Değişiklik | Yazan |
|-------|-------|-----------|-------|
| 2026-04-17 | 1.0 | İlk sürüm (Sprint 4) | Tech Lead |
