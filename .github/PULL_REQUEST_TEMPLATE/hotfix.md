<!--
HOTFIX PR — sadece P0/P1 production bug'lari icin.
Feature veya enhancement icin normal PR template kullan.
Bkz: docs/HOTFIX_PROCEDURE.md
-->

## Issue

- **Issue ID:** <!-- JIRA-XXXX / #123 / SEC-XXX -->
- **Severity:** <!-- P0 / P1 -->
- **Detect source:** <!-- Sentry / monitoring / user report / security scan -->
- **Detect time:** <!-- yyyy-mm-dd HH:MM UTC -->

## Root Cause (1 cumle)

<!-- Ornek: "iyzico callback imzasi prod config'te yanlis header adindan okunuyordu." -->

## Impact

- **Etkilenen kullanici:** <!-- sayi veya % -->
- **Etkilenen feature:** <!-- checkout / admin panel / api v1 -->
- **Gelir etkisi:** <!-- varsa -->
- **Data integrity etkisi:** <!-- evet/hayir — varsa aciklama -->

## Fix

<!--
Degisikligin ozeti, maksimum 5 madde.
Kod bolumleri genis degilse kismen yapistirilabilir.
-->

- ...

## Regression Test

- [ ] Ayni bug'in tekrarlanmasini yakalayacak test eklendi
- [ ] Test lokal yesil (fix oncesi testi calistirdim, fail ediyordu — dogrulama yaptim)

## Pre-merge Checklist

- [ ] `vendor/bin/phpunit` yesil
- [ ] `vendor/bin/phpstan analyse --no-progress` 0 error
- [ ] `composer audit` uyari yok
- [ ] `php -l <degisen-dosyalar>` syntax OK
- [ ] Branch `main`'den acildi (develop'tan DEGIL)
- [ ] Commit message `hotfix(<scope>): <kisa>` formatinda
- [ ] Ikinci bir senior engineer approve etti

## Deploy Plan

- [ ] Blue env'e deploy edilecek (`make deploy-blue` veya CI auto)
- [ ] Smoke test sahibi: @<kullanici>
- [ ] Promote sahibi: @<kullanici>
- [ ] Rollback plani hazir: `make deploy-rollback`

## Post-Deploy Plan

- [ ] 10 dk observability window (error rate, p95 latency)
- [ ] RCA toplantisi planlandi (48 saat icinde)
- [ ] RCA dokumani yeri: `docs/rca/<tarih>-<slug>.md`
- [ ] Develop'a merge/rebase yapilacak
- [ ] CHANGELOG.md'ye hotfix entry eklenecek

## On-Call

- **On-call engineer:** @<kullanici>
- **Sorumlu product owner:** @<kullanici>

---

/label hotfix
