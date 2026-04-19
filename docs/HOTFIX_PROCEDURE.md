# Hotfix Procedure

**Amac:** Production'daki kritik bir sorunu (outage, security incident, data corruption)
normal release cycle'ini beklemeden hizla canliya cikarmak.

**Kapsam:** Sadece P0/P1 ciddiyetinde bug'lar ve guvenlik yamalari. Feature eklemek
YASAK — hotfix branch'i feature'a donusturulmemeli.

**Sahibi:** Tech Lead + On-Call Engineer. Sprint 4 itibariyle zorunlu sureclere bagli.

---

## 1. Ne Zaman Hotfix?

| Durum                                           | Hotfix mi? |
|-------------------------------------------------|------------|
| Production down (5xx > %50)                     | EVET       |
| Odeme/siparis akisi kirik                       | EVET       |
| Data leakage / auth bypass                      | EVET       |
| Admin paneli acilmiyor                          | EVET       |
| UI polish / copy degisikligi                    | HAYIR      |
| Performance iyilestirmesi (SLA disi)            | HAYIR      |
| Yeni feature / enhancement                      | HAYIR      |

Kararsizsan: Tech Lead'e sor, sonra hotfix branch ac.

---

## 2. Branch Naming

```
hotfix/<issue-id>-<kisa-aciklama-kebab-case>
```

Ornekler:
- `hotfix/JIRA-4711-odeme-callback-500`
- `hotfix/SEC-023-session-fixation`
- `hotfix/P0-admin-login-broken`

Kurallar:
- Issue ID zorunlu (JIRA, GitHub Issue, Sentry)
- Slug 40 karakter altinda
- Sadece kucuk harf, tire

---

## 3. Checkout Source

**Her zaman `main`'den branch ac.** `develop` veya feature branch'ten ASLA.

```bash
git fetch origin
git checkout main
git pull origin main
git checkout -b hotfix/<issue-id>-<slug>
```

**Neden main?** Prod'da calisan kod main'de. Develop'ta henuz release edilmemis
regression'lar bulunabilir — hotfix'i bulastirma.

---

## 4. Commit + Test Kurallari

- Minimal degisiklik. Refactor yapma, sadece bug fix.
- Her commit test icermeli (regression test sart).
- Commit message formati:
  ```
  hotfix(<scope>): <kisa>
  
  Fixes: <issue-id>
  Root cause: <tek cumle>
  Impact: <etkilenen kullanici/feature>
  ```

Ornek:
```
hotfix(odeme): iyzico callback 500 hatasi

Fixes: JIRA-4711
Root cause: imza dogrulama header'i eksik
Impact: %100 iyzico odemeleri basarisiz
```

---

## 5. CI Kosullari

**Hotfix branch'lerinde hizli CI pipeline calisir.** E2E ve asset build'ler ATLANIR
— sadece guvenlik kritigi korunur.

Zorunlu (bloke eder):
- [x] `vendor/bin/phpunit` (tum testler)
- [x] `vendor/bin/phpstan analyse` (0 error)
- [x] `composer audit` (security advisory check)
- [x] Syntax check (`php -l`)

Opsiyonel (bilgilendirme — bloke etmez):
- [ ] E2E Playwright testleri
- [ ] `npm run build` (asset'ler zaten prod'da)
- [ ] Lighthouse skorlari
- [ ] Visual regression snapshot'lar

`.github/workflows/hotfix.yml` (eger eklenecekse) bu matris'i uygular:
```yaml
on:
  push:
    branches: ['hotfix/*']
  pull_request:
    branches: [main]
```

---

## 6. Pull Request

- Template: `.github/PULL_REQUEST_TEMPLATE/hotfix.md` otomatik yuklenir
- Reviewer: minimum 1 senior engineer (CODEOWNERS zorunlu)
- Merge stratejisi: `Squash and merge` (tek temiz commit main'e)
- Labels: `hotfix`, `p0` veya `p1`, `needs-backport` (gerekirse)

---

## 7. Deploy: Blue-Green Fast Track

1. PR merge edilir -> main'e dusurulur
2. CI yesil -> `main` otomatik **blue** environment'a deploy olur (5-7 dk)
3. Smoke test (manuel veya otomatik):
   - `/api/health.php/live` 200
   - Admin login calisiyor
   - 1 test siparisi acilip iptal edilebiliyor
4. **On-Call Engineer** DNS/load balancer switch yapar:
   ```bash
   make deploy-promote ENV=blue
   ```
5. 10 dakika observability window — error rate + p95 latency izlenir
6. Geri sorun gorulurse: `make deploy-rollback` ile 30 sn'de eski environment geri gelir

**NOT:** Bu dokuman Sprint 4'te yazildi. Blue-green infrastructure QA+DevOps
agent'i tarafindan hazirlaniyor. `make deploy-promote` komutu henuz
makefile'da yoksa DevOps'a basvur.

---

## 8. Post-Hotfix Prosedur

Hotfix prod'a gittikten **en gec 48 saat icinde**:

### 8.1 Retrospektif Toplanti
- Katilim: hotfix'i yapan, reviewer, on-call, product owner
- Sure: 30 dakika
- Format: blameless — kisi degil, sistem elestirilir

### 8.2 Root Cause Analysis (RCA)
Sablon:

```markdown
# RCA: <kisa baslik>

**Tarih:** <yyyy-mm-dd>
**Sure:** Soruna baslangic -> cozum ne kadar surdu?
**Etkilenen kullanici:** <sayi veya %>
**Gelir/SLA etkisi:** <varsa>

## Neden Oldu?
1. Immediate cause: <teknik sebep>
2. Contributing factors: <test eksigi, review bypass, vb>
3. Root cause: <sistemik — "neden bu test yoktu?">

## Timeline
- T+0: <ilk sinyal>
- T+X: <detect>
- T+Y: <mitigation>
- T+Z: <full fix>

## Ne Iyi Gitti?
- ...

## Ne Iyilestirilebilir?
- ...

## Action Items
- [ ] <sahibi> <tarih> <gorev>
```

Kaydetme yeri: `docs/rca/<tarih>-<slug>.md`

### 8.3 Regression Test Eklenmeli
Hotfix PR'inda regression test ZATEN vardi (madde 4). Ama eksikse **ayri** bir
PR ile eklenmeli — hotfix PR'ina ek yapmak merge sonrasinda tarihi bulandirir.

### 8.4 Develop/Main Sync
Eger hotfix main'den actildi ise develop'a merge/rebase gerekir:
```bash
git checkout develop
git merge main  # veya rebase
git push origin develop
```

### 8.5 Changelog Guncelle
`CHANGELOG.md` > `## [Hotfix X.Y.Z] - <tarih>` bolumu eklenir.

---

## 9. Hotfix SLA

| Severity | Detect->Fix SLA | Detect->Deploy SLA |
|----------|-----------------|---------------------|
| P0 (outage) | 1 saat         | 2 saat              |
| P1 (critical bug) | 4 saat    | 8 saat              |
| P2 (important)    | 1 gun     | 2 gun               |

SLA asilirsa: incident olarak eskalasyon, director'a bildirilir.

---

## 10. Hotfix Kontrol Listesi (Checklist)

```
[ ] Issue ID var ve tracker'da
[ ] Severity belirlenmis (P0/P1)
[ ] Branch main'den acildi
[ ] Branch naming: hotfix/<id>-<slug>
[ ] Commit: regression test icerdi
[ ] phpunit + phpstan + composer audit yesil
[ ] PR template doldurulmus
[ ] 1+ senior reviewer approved
[ ] Squash merge yapildi
[ ] Blue env deploy yesil
[ ] Smoke test gecti
[ ] Promote yapildi
[ ] 10 dk observability window temiz
[ ] Post-hotfix: RCA draft olusturuldu
[ ] Develop branch sync edildi
[ ] CHANGELOG guncellendi
```

---

**Son guncelleme:** 2026-04-17 (Sprint 4 — Production Launch Backend)
