# Sprint 2 — Tech Lead Görevleri (admin/urunler.php Controller/View Refactor)

## Plan

- [x] Mevcut yapıyı oku: admin/urunler.php, BaseController, UrunService, helpers
- [x] src/Controllers/Admin/UrunController.php oluştur (index, store, update, destroy, toggleAktiflik)
- [x] BaseController'a admin-web yardımcıları ekle (view, redirect, flash, requireCsrf, requirePermission, backwards-compat koru)
- [x] views/admin/urunler/index.php oluştur (sadece HTML + echo)
- [x] views/admin/urunler/_row.php partial (ürün satırı)
- [x] views/admin/urunler/_delete-modal.php partial
- [x] admin/urunler.php'i router'a dönüştür (~30 satır)
- [x] PHP syntax check (php -l) — tüm yeni/değişen dosyalar
- [x] Smoke test: web isteği ile sayfa render edilip edilmediğini doğrula
- [x] docs/ADMIN_CONTROLLER_PATTERN.md yaz
- [x] CLAUDE.md'ye Sprint 2 pattern dersini ekle

## Review
(Aşağıda "Prod Raporu" bölümünde)
