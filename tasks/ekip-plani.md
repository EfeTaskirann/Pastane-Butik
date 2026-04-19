# EKİP PLANI — TATLI DÜŞLER PASTANE v1.0

**Hedef:** 8 hafta sonunda production-ready v1.0
**Tarih:** 2026-04-17
**Roadmap:** [senior-dev-gelistirme-raporu.md](senior-dev-gelistirme-raporu.md)

---

## 📊 EKİP BOYUT SENARYOLARI

| Senaryo | Kişi | Süre | Toplam Kişi-Hafta | Tahmini Aylık Maliyet* | Risk |
|---|---|---|---|---|---|
| **Lean** | 2 | 14-16 hafta | 28-32 | 60-80k ₺ | 🔴 Yüksek — bottleneck, burnout, tek noktada bilgi |
| **Optimal** ✅ | 4 | 8 hafta | 32 | 100-140k ₺ | 🟢 Düşük — uzmanlık ayrışması var |
| **Agresif** | 6 | 6 hafta | 36 | 170-220k ₺ | 🟡 Orta — koordinasyon karmaşık, overhead |

*Orta seviye geliştirici, Türkiye market ortalaması (2026 nominal). Freelance/agency ≠ in-house.

> **Tavsiye:** 4 kişilik **Optimal** senaryo — bu proje ölçeği için en iyi cost/risk dengesi.

---

## 👥 OPTIMAL EKİP (4 KİŞİ) — DETAYLI

### 1️⃣ Tech Lead (Senior Fullstack PHP) — `%100`
**Seviye:** 10+ yıl PHP/MySQL, mimari deneyimi
**Sorumluluklar:**
- Mimari kararlar, Controller refactor yönlendirmesi
- Code review (her PR)
- Kritik güvenlik işleri (CSP, secret rotation, RBAC)
- OdemeService, MasaSiparisService gibi kompleks servisler
- Junior ekibe mentoring

**Sprint yükü:**
- Sprint 0: CSP + secret rotation (kritik)
- Sprint 1: RBAC middleware + N+1 audit
- Sprint 2: Controller refactor pilot
- Sprint 3: OpenAPI docs

**Aranan skiller:** PHP 8.1+, PSR, DI, PDO, JWT, Docker, CI/CD

---

### 2️⃣ Backend Developer (Senior PHP) — `%100`
**Seviye:** 5+ yıl PHP, test yazma alışkanlığı
**Sorumluluklar:**
- Servis/Repository katmanı test yazma
- Yeni feature implementasyonu (email, audit log, 2FA, backup)
- Database migration yazma
- API endpoint geliştirme

**Sprint yükü:**
- Sprint 1: Unit testler (Odeme + MasaSiparis + Siparis)
- Sprint 1: Email notification sistemi
- Sprint 2: Backup UI + Settings UI + Audit trail kolonları
- Sprint 3: Swagger annotation'ları

**Aranan skiller:** PHPUnit, PDO, REST API, PHP 8.1+, Composer

---

### 3️⃣ Frontend Developer (Mid) — `%100`
**Seviye:** 4+ yıl HTML/CSS/JS, responsive, a11y deneyimi
**Sorumluluklar:**
- Admin sayfalarından inline `onclick` temizleme (CSP)
- Mobile UX audit (özellikle QR menü)
- WCAG AA erişilebilirlik
- Dark mode CSS
- Admin UI refactor (view katmanı)

**Sprint yükü:**
- Sprint 0: CSP onclick düzeltmeleri (Tech Lead ile birlikte)
- Sprint 1: Admin Activity Log UI (Backend ile paralel)
- Sprint 2: 2FA UI + Settings UI frontend
- Sprint 3: Mobile + a11y + dark mode

**Aranan skiller:** Vanilla JS, CSS variables, responsive design, Lighthouse, WCAG

---

### 4️⃣ QA + DevOps (Junior-Mid) — `%100`
**Seviye:** 1-3 yıl, öğrenmeye açık hibrit profil
**Sorumluluklar:**
- Test planı koşma ([test-plani-4-kisi.md](test-plani-4-kisi.md)) + regression
- CI/CD pipeline (GitHub Actions)
- Docker multi-container setup
- Sentry + log rotation + health check
- Backup script + monitoring

**Sprint yükü:**
- Sprint 0: CI/CD pipeline kurulum + Docker pin
- Sprint 1: Sentry + Health check + Test koşum
- Sprint 2: Log rotation + Backup cron + Monitoring dashboard
- Sprint 3: E2E test (Playwright) kurulum
- Her sprint: Regression test koşum

**Aranan skiller:** GitHub Actions, Docker, bash, temel PHP, Playwright/Cypress, Postman

---

## 📅 SPRINT DAĞILIMI (Kim, Ne, Ne Zaman)

### 🚀 Sprint 0 — Production Blocker (Hafta 1)

| Kişi | Görev | Efor |
|---|---|---|
| Tech Lead | `.env` secret rotation + git history clean | 1 gün |
| Tech Lead | CSP inline onclick strateji + pilot admin/urunler.php | 2 gün |
| Tech Lead | Code review + mentoring | ~%20 |
| Frontend | CSP onclick temizleme — kategoriler/masalar/takvim | 3 gün |
| Backend | `.env`/`.env.example` senkronizasyon + env() default | 0.5 gün |
| Backend | Password hash explicit + rate limit header'ları | 1 gün |
| QA+DevOps | GitHub Actions pipeline (PHPUnit + PHPStan + PHPCS) | 2 gün |
| QA+DevOps | Dockerfile PHP version pin + compose review | 1 gün |
| QA+DevOps | Test planı ilk koşum — baseline kırıklar | 2 gün |

**Sprint 0 Çıktısı:** CSP compliant, secret'ler güvenli, CI çalışıyor, baseline test raporu

---

### 🎯 Sprint 1 — Temel Sağlamlaştırma (Hafta 2-3)

| Kişi | Görev | Efor |
|---|---|---|
| Tech Lead | RBAC middleware + role decorator | 3 gün |
| Tech Lead | N+1 query audit + critical fix | 2 gün |
| Tech Lead | Masa timeout cron job | 0.5 gün |
| Tech Lead | Code review | ~%20 |
| Backend | OdemeService testleri | 3 gün |
| Backend | MasaSiparisService + SiparisService testleri | 3 gün |
| Backend | Email notification sistemi (SMTP + template) | 3 gün |
| Frontend | Admin Activity Log UI (tablo + filtreler) | 4 gün |
| Frontend | Loading state + toast notification component | 2 gün |
| QA+DevOps | Sentry entegrasyon + hook | 2 gün |
| QA+DevOps | Health check endpoint güçlendirme | 0.5 gün |
| QA+DevOps | Log rotation cron | 0.5 gün |
| QA+DevOps | Sprint 0 regression + Sprint 1 test planı | 3 gün |

**Sprint 1 Çıktısı:** %60+ test coverage, RBAC aktif, email gönderimi, Sentry alerting, audit UI

---

### 🔧 Sprint 2 — Operasyonel Excellence (Hafta 4-5)

| Kişi | Görev | Efor |
|---|---|---|
| Tech Lead | Admin Controller refactor — urunler.php pilot | 4 gün |
| Tech Lead | Refactor review + pattern doküman | 2 gün |
| Tech Lead | Code review | ~%20 |
| Backend | Backup & Restore UI + cron script | 3 gün |
| Backend | Audit trail kolonları (created_by/updated_by) migration + middleware | 2 gün |
| Backend | Settings Management backend (key-value tablosu) | 2 gün |
| Backend | SMS notification (Netgsm/Twilio) | 2 gün |
| Frontend | 2FA Setup UI (QR kod + backup codes) | 3 gün |
| Frontend | Settings Management UI (admin > ayarlar) | 3 gün |
| Frontend | Form validation UX (inline error, real-time) | 2 gün |
| QA+DevOps | Backup cron + monitoring | 1 gün |
| QA+DevOps | Prometheus metrics endpoint | 2 gün |
| QA+DevOps | Sprint 2 regression | 3 gün |
| QA+DevOps | Staging environment kurulum | 2 gün |

**Sprint 2 Çıktısı:** Backup/Restore UI, 2FA, Settings UI, SMS bildirim, audit trail, staging

---

### 💎 Sprint 3 — UX + DX (Hafta 6-7)

| Kişi | Görev | Efor |
|---|---|---|
| Tech Lead | OpenAPI/Swagger annotations + UI mount | 4 gün |
| Tech Lead | API versioning stratejisi dokümanı | 1 gün |
| Tech Lead | Final security audit + penetration test | 2 gün |
| Tech Lead | Code review | ~%20 |
| Backend | Remaining endpoint swagger annotations | 3 gün |
| Backend | Database migration consolidate (SQL→PHP) | 2 gün |
| Backend | Cache integration — hot paths | 2 gün |
| Frontend | Mobile UX audit + fixes (QR menü öncelik) | 4 gün |
| Frontend | WCAG AA audit + contrast/focus/ARIA | 3 gün |
| Frontend | Dark mode CSS + system preference | 2 gün |
| QA+DevOps | Playwright E2E test kurulum + 10 kritik flow | 5 gün |
| QA+DevOps | PHP-CS-Fixer + pre-commit hooks (captainhook) | 1 gün |
| QA+DevOps | Developer onboarding doc + Makefile | 2 gün |

**Sprint 3 Çıktısı:** OpenAPI docs, mobile UX, a11y compliant, dark mode, E2E testler

---

### 🎉 Sprint 4 — Production Launch (Hafta 8)

| Kişi | Görev | Efor |
|---|---|---|
| Tüm ekip | Final regression + smoke test | 2 gün |
| Tech Lead | Production deployment planı + go/no-go karar | 1 gün |
| QA+DevOps | Blue-green deployment prosedürü | 2 gün |
| QA+DevOps | Monitoring + alerting + runbook | 2 gün |
| Backend | Son bug fix + hotfix hazır | ongoing |
| Frontend | Copy polishing + son UI touch | ongoing |
| Tech Lead | Production cutover + post-launch monitoring | 1 gün |

**Sprint 4 Çıktısı:** v1.0 production live, monitoring aktif, runbook hazır

---

## 💸 2 KİŞİLİK MİNİMUM SENARYO (Bütçe Kısıtlıysa)

**Kim:**
- 1 Senior Fullstack (Tech Lead rolü üstlenir)
- 1 Mid Fullstack (backend ağırlıklı, gerektiğinde frontend)

**Süre:** 14-16 hafta (neredeyse 2 kat)

**Riskler:**
- ❌ Uzmanlık derinliği yok — mobile UX ve a11y için ekstra danışman gerekebilir
- ❌ Dedicated QA yok — developer kendi testini yazar, bias var
- ❌ DevOps görevleri öteleniyor — production'a CI/CD zayıf çıkılma riski
- ❌ Burnout — tek senior 14 hafta boyunca her şeyden sorumlu
- ❌ Bilgi silosu — biri tatile çıkarsa proje durur

**Uygunluk:** Startup MVP mantığı, bütçe < 100k ₺

---

## ⚡ 6 KİŞİLİK AGRESİF SENARYO (Tempo Kritikse)

**Kim:**
- 1 Tech Lead (Senior Fullstack)
- 2 Backend Developer
- 1 Frontend Developer
- 1 QA Engineer
- 1 DevOps Engineer

**Süre:** 6 hafta

**Artıları:**
- ✅ Paralel sprint execution — 2 backend dev = 2 katı hız
- ✅ Dedicated DevOps + QA = production excellence
- ✅ Risk dağılımı iyi (bilgi silosu yok)

**Eksileri:**
- ❌ Koordinasyon overhead — daily standup + PR merge conflict
- ❌ Maliyet %60+ fazla ama hız %25 daha hızlı (verimsizlik)
- ❌ Butik pastane ölçeğinde overkill

**Uygunluk:** Sert deadline (örn. Ramazan/yılbaşı launch), kurumsal müşteri

---

## 🔄 ALTERNATİF MODELLER

### Model A: 4 Full-time (önerilen)
- Uzun vadeli proje sahipliği, bakım dahil

### Model B: 3 Full-time + 1 Part-time Danışman
- Tech Lead + 2 Mid full-time + 1 senior danışman (haftada 1 gün)
- Maliyet %25 daha düşük, kalite büyük oranda korunur

### Model C: Agency Outsource
- Türkiye'de orta ölçekli agency: 150-200k ₺/ay
- Artı: Hızlı onboarding, eksi: kurumsal bilgi birikimi dışarıda kalır

### Model D: Freelancer Mesh
- 1 senior PM + 4 freelancer (part-time)
- Artı: Maliyet düşük, eksi: koordinasyon zor, kalite değişken

---

## ✅ TAVSİYE ÖZETİ

| Kriter | Öneri |
|---|---|
| Ekip boyutu | **4 kişi (Optimal)** |
| Çalışma modeli | 3 full-time + 1 haftalık danışman (Model B) hibrit de iyi |
| Süre | 8 hafta (Sprint 0-3) + Sprint 4 launch |
| Çalışma şekli | 2 haftalık sprint, daily 15dk standup, Cuma demo |
| Kod yönetimi | Git flow — main korumalı, feature branch, PR + code review zorunlu |
| Toplantı disiplini | Pazartesi sprint planning (1 saat), Cuma retrospektif (30 dk) |
| Proje yönetimi | Jira/Linear/Trello — issue → PR otomatik bağla |

---

## 📈 BAŞARI KRİTERLERİ

Ekip doğru kurulmuş sayılması için:

- [ ] 2 sprint sonunda test coverage ≥ %50 (hedef: Sprint 2 bitimde)
- [ ] Her sprint'in son günü demo edilebilir
- [ ] CI pipeline ilk haftadan çalışıyor, pass rate %95+
- [ ] Sprint velocity stabil (±%20 dalgalanma)
- [ ] Her ekip üyesi en az 2 alanda backup edebilir (silo yok)
- [ ] Haftada < 5 saat meeting overhead

---

**Hazırlayan:** Senior Dev ekip planlaması
**Versiyon:** 1.0
**Son Güncelleme:** 2026-04-17
