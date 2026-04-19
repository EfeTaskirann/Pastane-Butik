# CodeCanyon Remediation Plan — 4-Agent Iterative Workflow

> **Kaynak:** `tasks/codecanyon-audit-report.md`
> **Amaç:** Audit raporundaki 22 aksiyonu 4-agent döngüsüyle kapatmak. Her iş paketi Implementer → Tester → Auditor → Reviewer zincirinden geçer, fail'de Implementer'a döner.

---

## Agent Rolleri

| Agent | Rol | Yetki | Tool Kapsamı |
|---|---|---|---|
| **A1 — Implementer** | İşi yapar (kod/dosya/asset üretir) | Read + Write + Edit + Bash | Tam |
| **A2 — Tester** | İşin çalıştığını somut testle kanıtlar (grep, komut çıktısı, dosya varlığı, HTTP status) | Read + Bash + Grep | Sadece doğrulama |
| **A3 — Auditor (Adversarial)** | A2'nin onayını **şüpheyle** kontrol eder: "gerçekten test etti mi, yoksa sadece dosya varlığına mı baktı?" Kanıt zinciri yetersizse reddeder. | Read + Bash + Grep | Bağımsız doğrulama |
| **A4 — Reviewer (Final Gate)** | Tüm iş paketlerini kapsayarak bakar: bütünlük, regresyon, side-effect, stil tutarlılığı. Herhangi birinde şüphesi varsa iş paketini açık tutar. | Tam | Kabul / ret |

### Kritik Kural
- A1 **asla kendi işini onaylamaz**.
- A2 **görsel inspeksiyon değil komut çıktısı** üretir (grep count, HTTP kodu, dosya MD5, komut exit code).
- A3 rolü **adversarial** — default "ret", A2'nin kanıtları delilli + tekrarlanabilir ise kabul eder.
- A4 birden fazla iş paketi biriktiğinde çalışır (batch review), tek tek değil.

---

## Döngü Mantığı

```
┌─────────────┐
│  Backlog    │  ← audit raporundaki 22 iş paketi (P0..P3)
└──────┬──────┘
       │ pick next (priority order)
       ▼
┌─────────────┐      fail (A1 eksik bıraktı)
│ A1 Implement│ ◄─────────────────────────────┐
└──────┬──────┘                                │
       │ "done" claim                          │
       ▼                                       │
┌─────────────┐      fail (testler geçmedi)    │
│ A2 Test     │ ──────────────────────────────►│
└──────┬──────┘                                │
       │ "pass" claim + kanıtlar               │
       ▼                                       │
┌─────────────┐      fail (kanıt yetersiz)    │
│ A3 Audit    │ ──────────────────────────────►│
└──────┬──────┘                                │
       │ "verified"                            │
       ▼                                       │
┌─────────────┐      fail (regresyon/bütünlük) │
│ A4 Review   │ ──────────────────────────────►┘
└──────┬──────┘
       │ "accepted"
       ▼
    CLOSED ✅
```

**İterasyon sayısı:** Her iş paketi max 3 iterasyon. 3. iterasyonda hala fail'se escalate (kullanıcıya gelir, scope/yaklaşım yeniden değerlendirilir).

**Paralellik:** Bağımsız iş paketleri paralel işlenebilir (A1/A2/A3 farklı paketlere atanır). Bağımlı olanlar (ör. `main_files/` yapısı inline CSS temizliğinden sonra) seri.

---

## İş Paketleri — Detaylı Liste

### Gösterim şeması (her paket için)
```
ID — Başlık (Öncelik)
DoD (Definition of Done): somut tamamlanma kriteri
Bağımlılık: öncelik sırası / diğer paketler
A1 talimatı: ne yapacak
A2 test kriteri: nasıl kanıtlanacak (grep/komut/dosya)
A3 audit soruları: nelere şüphe duyacak
A4 review noktası: bütünlük/regresyon
```

---

## P0 — Hard-Reject Önleyici (submit öncesi MUTLAKA)

### P0-01 — `.env` git history temizliği + secret rotation
- **DoD:** `git log --all -- .env` boş; tüm kullanımdaki secret'lar yeni değerlerle rotate; README'ye "fresh clone" notu
- **Bağımlılık:** Yok (ilk işlenecek — destructive, manuel onay gerekli)
- **A1:** `git filter-repo --path .env --invert-paths` (veya BFG). JWT_SECRET, METRICS_TOKEN, HEALTH_TOKEN, DB_PASSWORD yeniden üret (`openssl rand -hex 32`). `.env` güncel; `.env.example` placeholder. Force push onay sonrası.
- **A2:** `git log --all --full-history -- .env` (boş dönmeli). `git log -p --all | grep -E "JWT_SECRET=|METRICS_TOKEN=" | head` → sadece placeholder görmeli. Secret rotate edildi mi: `.env` ≠ `.env.example`, her değer minimum 32 byte entropy.
- **A3:** "`filter-repo` sadece HEAD'i mi temizledi, tüm ref'leri mi?" `git for-each-ref --format='%(refname)' | while read r; do git log $r -- .env; done` boş. Eski backup/mirror/fork repo var mı? (kullanıcıya sor). Pack'ler temizlendi mi: `git gc --aggressive --prune=now`.
- **A4:** Post-clean smoke: `.env` olmadan `composer install && php bin/db-migrate.php` çalışıyor mu? Prod env kontrolü runbook güncel mi?
- **Not:** Destructive — A1 çalıştırmadan önce kullanıcı onayı zorunlu.

### P0-02 — `uploads/.htaccess` PHP execution engeli
- **DoD:** `/uploads/*.php` requests → 403/404; `.htaccess` commit'li
- **A1:** `uploads/.htaccess` yaz: `php_flag engine off` + `<FilesMatch "\.(php|phtml|phar|pht|phtm|phps)$">Deny from all</FilesMatch>` + `Options -ExecCGI -Indexes`. Her alt klasör (`uploads/products/`, `uploads/kategoriler/`) için aynı veya inherit edilmeli.
- **A2:** Test: `uploads/shell.php` oluştur (`<?php echo "X"; ?>`), `curl -s -o /dev/null -w "%{http_code}" http://localhost/pastane/uploads/shell.php` → **403 veya 404** (200 ise fail). `.php` test dosyasını sonra sil.
- **A3:** "A2 gerçekten HTTP request yaptı mı, yoksa sadece `.htaccess` var mı diye baktı mı?" Bash output'ta `curl` + HTTP status kodu görmeli. Apache `AllowOverride All` olup olmadığı kontrol edilsin (`.htaccess` etkisizse engelsiz sayılır).
- **A4:** `uploads/.htaccess` dosya listesi: web-accessible mi? Image upload flow regresyonu (gerçek resim upload + serve hala çalışıyor mu).

### P0-03 — Inline `style="..."` 270 adet temizliği
- **DoD:** `grep -rnE 'style\s*=\s*"' --include="*.php" . | wc -l` ≤ 10 (zorunlu FOUC/runtime dinamik style hariç, her biri yorumla gerekçelendirilmiş)
- **A1:** 270 konumu batch olarak CSS class'a çıkar. Öncelik: `views/admin/urunler/*.php`, `index.php`, `menu/index.php`. Dinamik değer taşıyanlar `data-*` attribute + `<style>` nonce'lı block olmasın (CSS variable + class toggle tercih). Utility class lib (`.text-center`, `.mt-4`, `.hidden`, vs.) yoksa `assets/css/utilities.css` oluştur.
- **A2:** Önce: grep count baseline (≥270). Sonra: grep count ≤10. Kalan 10'un her biri `--include="*.php"` çıktısıyla listele ve yorumla gerekçeli. `npm run lint:css` varsa çalıştır. Browser smoke: `admin/urunler.php`, `index.php`, `views/admin/urunler/index.php` görsel regresyon (ekran görüntüsü before/after).
- **A3:** "A2 sadece count'a baktı, random 20 konum açıp gerçekten class'a dönüşmüş mü kontrol etti mi?" Random sample doğrulama: `grep -n 'class="' değişen dosyalarda yeni class'lar eklenmiş mi, utilities.css'te tanımlı mı (undefined class olursa sessizce fail). `is-hidden`/`is-visible` toggle JS tarafı etkilenen yerlerde hala çalışıyor mu.
- **A4:** CSS bundle büyüme oranı: yeni utilities.css ≤ 15 KB. `docs/BROWSER_COMPAT_MATRIX.md` güncelliği. Dark/Summer/Winter theme regresyonu: utility class'lar tema'dan etkileniyor mu?

### P0-04 — `LICENSE.txt`, `CREDITS.txt`, `CHANGELOG.md`
- **DoD:** 3 dosya root'ta commit'li; içerik CodeCanyon spec'ine uygun
- **A1:**
  - `LICENSE.txt`: Envato Regular + Extended License açıklaması (resmi şablon kopyası).
  - `CREDITS.txt`: `package.json` + `composer.json` taraması + tüm font/asset/icon kaynakları. Her satır: `<Kütüphane> <versiyon> — <Lisans> — <URL>`. Google Fonts (Playfair + Poppins — OFL), Chart.js (MIT), Flatpickr (MIT), SortableJS (MIT), SweetAlert2 (MIT), FontAwesome versiyonu varsa Free (CC BY 4.0) doğrulama.
  - `CHANGELOG.md`: `git log --oneline --tags` → semver formatı (1.0.0 initial release + sprint milestone'ları).
- **A2:** 3 dosya var mı: `ls -la LICENSE.txt CREDITS.txt CHANGELOG.md`. Her biri > 500 byte, boilerplate değil. CREDITS.txt'te `package.json`'da olan tüm paketler listelenmiş mi: `jq -r '.dependencies|keys[]' package.json | while read p; do grep -q "$p" CREDITS.txt || echo "MISSING: $p"; done`.
- **A3:** "LICENSE.txt CodeCanyon'un şu anki resmi şablonuyla eşleşiyor mu, 2020 versiyonu mu?" CREDITS'te kayıp entry: font Awesome kullanımı var mı (`grep -r "fa-" --include="*.php"`), varsa credit'te yer alıyor mu. `CHANGELOG.md` semver uyumlu (1.0.0 öncesi 0.x olmalı, hem frontmatter hem sürüm satırları).
- **A4:** Bu 3 dosya `.gitattributes`'te export-ignore DEĞİL (ZIP'e girecek).

### P0-05 — `main_files/ + documentation/ + licensing/` paket yapısı
- **DoD:** `bin/build-submission.php` (veya `Makefile` target) `dist/pastane-submission-v1.0.0.zip` üretir, yapı spec'e uygun
- **Bağımlılık:** P0-04 (LICENSE/CREDITS tamamlanmalı), P0-06 (documentation içeriği)
- **A1:** `Makefile`'a `submission-zip` target'ı ekle. Script: source → `dist/build/main_files/` (kod), `dist/build/documentation/` (P0-06 çıktıları), `dist/build/licensing/` (P0-04 dosyaları), `dist/build/README.txt`. `.gitattributes` export-ignore uygulanmış olmalı. `zip -r` ile paketle.
- **A2:** `make submission-zip`; zip'i aç ve yapı kontrol: `unzip -l dist/pastane-submission*.zip | grep -E "^.+(main_files|documentation|licensing)/"`. ZIP'te `node_modules/`, `.env`, `tests/`, `docs/`, `.git/` **yok**: `unzip -l ... | grep -E "(node_modules|\.env|\.git/|tests/|tasks/)"` boş.
- **A3:** "ZIP'i gerçekten bir temp'e açıp `main_files/` içinde PHP koşar mı denedi mi?" Fresh temp dir + unzip + `php -S localhost:9999 -t main_files/` smoke. ZIP boyutu makul (50-150 MB arası, 500 MB+ ise node_modules sızıntı şüphesi).
- **A4:** README.txt zip root'ta buyer'ı documentation/install.html'e yönlendiriyor mu.

### P0-06 — Buyer-facing documentation (İngilizce, ekran görüntülü)
- **DoD:** `documentation/` klasöründe 6 HTML dosyası: index, installation, configuration, admin-guide, user-guide, faq
- **A1:** Her HTML: doctype + CSS (standalone, internal `<style>` ok çünkü bu paket-içi), ekran görüntülü adım adım. Ekranlar `documentation/screenshots/*.png` (mevcut `screenshots/`'dan yeniden boyutlandır veya yenile). İngilizce dilbilgisi kontrolü.
  - `installation.html`: PHP 8.1+, MySQL 8.0+, Apache+mod_rewrite+mod_headers, Composer, `.env.example` → `.env` copy, `php bin/db-migrate.php`, `php bin/db-seed.php`, admin login.
  - `configuration.html`: `.env` her key + açıklama + default, SMTP setup, payment gateway toggle (Iyzico placeholder disclaimer), SMS driver seçimi.
  - `admin-guide.html`: dashboard → products → categories → orders → messages → calendar → settings (2FA, backup, mail, users) — her biri için 1-2 screenshot.
  - `user-guide.html`: customer menu flow, sipariş verme, takip.
  - `faq.html`: 10 soru (permission errors, SMTP not sending, CSRF token expired, install fails, ..)
- **A2:** `ls documentation/*.html` 6 dosya; her dosya > 4 KB; `grep -c "<img" documentation/*.html` her biri ≥ 3 image; İngilizce LT regex: non-ASCII check (`grep -P "[^\x00-\x7F]" documentation/*.html` → sadece HTML entity, Türkçe karakter değil).
- **A3:** "Screenshot'lar gerçekten güncel UI mı, Sprint 0 ekranları değil mi?" Random 5 screenshot'ı aç, tarih kontrolü + içerik (dark mode toggle görünüyor mu, yeni feature'lar var mı). Broken link: `documentation/index.html`'deki tüm `<a href>` dosya yolu var mı.
- **A4:** Buyer'ın 30 dk içinde kuruluma alması gerekiyor — gerçekten okunabilir mi? Manuel spot check bir dokümandan.

### P0-07 — Uninstall script
- **DoD:** `php bin/uninstall.php --confirm` tüm tabloları drop + uploads/ temizler + storage/logs/ temizler + .env'yi siler
- **A1:** `bin/uninstall.php`: CLI-only guard, `--confirm` olmadan abort, `ALLOWED_TABLES`'daki tüm tabloları `DROP TABLE IF EXISTS` (FK disable → drop → FK enable), `uploads/` içeriği sil (`.gitkeep` koru), `storage/logs/*.log` temizle, `.env` sil. Log'la: `storage/logs/uninstall-<timestamp>.log` (silinmemeli).
- **A2:** Fresh test DB kur → migrate+seed → `php bin/uninstall.php --confirm` → `SHOW TABLES` boş, `uploads/products/` sadece `.gitkeep`, `.env` yok. `--confirm` olmadan exit code ≠ 0.
- **A3:** "Foreign key cycle varsa DROP order'ı doğru mu?" `information_schema.REFERENTIAL_CONSTRAINTS` sorgusu + dependency graph. Script tekrar çağrılabilir (idempotent) mi?
- **A4:** Documentation/faq.html'de "how to uninstall" sorusu cevaplanmış mı.

### P0-08 — Marketing assets (thumbnail / icon / preview)
- **DoD:** `marketing/` klasöründe 1× 590×300 thumbnail, 1× 80×80 icon, 6× 1370×752 preview PNG/JPG
- **A1:** Design tool (Figma/Canva export) veya mevcut screenshot'ları crop + branding overlay. Her preview farklı bir modül (1-homepage, 2-menu, 3-admin-dashboard, 4-product-edit, 5-order-tracking, 6-dark-mode).
- **A2:** `identify marketing/*.png marketing/*.jpg` (ImageMagick) — her dosya boyutu doğru mu: 590×300, 80×80, 1370×752. Dosya ≤ 2 MB her biri.
- **A3:** "6 preview gerçekten farklı modüller mi yoksa aynı ekranın crop'ları mı?" Perceptual hash karşılaştırma. Branding/logo tutarlı mı (tüm preview'larda aynı site-logo konumu).
- **A4:** Product page draft (CodeCanyon üzerinde değil, `marketing/product-page-draft.md`) — tagline, 5 bullet feature, USP.

---

## P1 — Puan Yükseltici (strongly recommended)

### P1-09 — IDOR audit & fix (SiparisRepository + customer routes)
- **DoD:** `/menu/siparis-takip.php?id=X` başkasının siparişine erişemiyor; `api/v1/siparisler/{id}` müşteri JWT olmadan 401, farklı müşteri 403
- **A1:** `SiparisRepository::find($id)` çağrısına `musteri_id` ek filtre (veya signed token+telefon ikili). Public takip için: sipariş oluşturulurken `takip_token = bin2hex(random_bytes(16))` DB'ye yaz, URL'de `id` yerine `token` kullan.
- **A2:** 2 farklı müşteri oluştur → sipariş ver → her birinin token'ını al → A'nın token'ıyla B'nin siparişini iste → 404. Sayısal ID enumeration testi: `curl /menu/siparis-takip.php?id=1..100` → 0 leak.
- **A3:** "Token'lar gerçekten `random_bytes` mi, sequential mi? Token süresi ne? Brute-force koruması?" Rate limit token denemesi (`api/v1/siparisler/track/{token}` 5/dk).
- **A4:** Mevcut siparişler için migration gerekir (null token'lar). Eski URL'ler kırılır mı? Backward compat gerekliyse redirect.

### P1-10 — Image optimization + lazy loading
- **DoD:** `uploads/products/*.{jpg,png}` WebP'e convert edilir (SrcSet/picture fallback); tüm `<img>` etiketlerinde `loading="lazy"` (hero hariç)
- **A1:** Upload handler'da Intervention Image veya native GD: resize + WebP encode (fallback JPEG). Mevcut içerik için `bin/optimize-images.php` one-shot. Template'lerde `<picture><source type="image/webp" ...><img ...></picture>` pattern.
- **A2:** `find uploads/products -name "*.webp" | wc -l` > 0; `find uploads/products -name "*.jpg" -size +500k | wc -l` = 0 (hepsi optimize). `grep -rnE '<img[^>]*loading="lazy"' --include="*.php" . | wc -l` artış (baseline 1 → 30+).
- **A3:** "Hero image'a lazy verildi mi? (LCP'yi düşürür — fail.) Tüm kritik görseller eager, geri kalanı lazy mi?" Lighthouse score önce/sonra.
- **A4:** Browser compat: WebP Safari 14+ desteği, fallback işliyor mu.

### P1-11 — CSS/JS minification build pipeline
- **DoD:** `npm run build` veya `make build` → `assets/dist/*.min.{css,js}` üretir; HTML `APP_ENV==production` ise min versiyonları yükler
- **A1:** `package.json` scripts: esbuild/rollup/terser + cssnano. `npm run build` ile `assets/dist/`'e yaz. `includes/asset.php` helper: `asset('style.css')` → prod'da `dist/style.min.css`, dev'de raw.
- **A2:** Build artifact varlığı; min dosya size < orijinal * 0.6. Source map'ler prod ZIP'te **YOK** (`.gitattributes` export-ignore `assets/dist/*.map`). Min CSS/JS syntax valid (`esbuild --validate` veya parse).
- **A3:** "Min versiyon gerçekten referans ediliyor mu, yoksa hala raw mı yükleniyor?" `curl` ile production build bir kez çalıştır, HTML output'ta `dist/*.min.css` görünmeli.
- **A4:** Cache busting: hash-based filename veya `?v=<mtime>`. CDN/cache header'ları hala doğru.

### P1-12 — `.gitattributes` node_modules export-ignore + cleanup
- **DoD:** `.gitattributes`'te `node_modules/ export-ignore`; ZIP'te node_modules yok
- **A1:** `.gitattributes`'e satır ekle. P0-05 zip build'inde test.
- **A2:** `git archive --format=zip HEAD | unzip -l - | grep node_modules` boş.
- **A3:** "Sadece `.gitattributes`'e yazıldı mı yoksa test edildi mi?" Fresh `git archive` + grep.
- **A4:** Diğer gereksiz: `.agent-logs/`, `tasks/`, `.claude/`, `.github/`, `CLAUDE.md` — hepsi export-ignore mi?

### P1-13 — `console.log` kalıntılarını temizle
- **DoD:** `grep -rn "console\." assets/js/ --include="*.js" | grep -v vendor | wc -l` = 0 (vendor min.js hariç)
- **A1:** `assets/js/main.js:51,1034,1215`, `assets/js/modules/forms.js:248` kalıntıları: ya tamamen sil ya `if (window.__DEBUG) console.log(...)` wrap. Production build'de terser `drop_console: true`.
- **A2:** Grep sonucu; ayrıca runtime: browser DevTools console, hiç `log/warn/error` (minified vendor hariç).
- **A3:** "Sadece silinen 4 yer mi, başka eklenenler var mı?" Full grep her JS dosyasında.
- **A4:** Error logging gerçekten gerekli yerlerde (ör. payment fallback) Sentry'ye gidiyor mu yoksa sessiz mi yutuldu.

### P1-14 — `database.sql` re-generate (migration canonical)
- **DoD:** `database.sql` güncel şemayı yansıtır; `docs/DB_SCHEMA_AUDIT.md` 0 diff
- **A1:** Fresh DB'de migrate → `mysqldump --no-data pastane_db > database.sql` (şema) + `bin/db-seed.php` çıktısı ile demo data dump'ı ekle. `database.sql` header: "Generated from migrations at <date> — DO NOT EDIT MANUALLY".
- **A2:** `php bin/db-schema-diff.php` → 0 fark. Fresh kurulum 2 yolla: (a) `database.sql` import, (b) `php bin/db-migrate.php` — her ikisi de aynı şemaya ulaşmalı (`mysqldump --no-data` karşılaştır).
- **A3:** "Migration çalıştırmak ile dump import aynı mı? Karakter set? Trigger/view?" Byte-level diff.
- **A4:** Installation.html'de "use migrations OR database.sql (both produce identical schema)" notu.

---

## P2 — Bonus Özellikler (scope kararına bağlı)

### P2-15 — i18n (TR/EN)
- **DoD:** `lang/tr.php` + `lang/en.php`; `t('key')` helper; `?lang=en` switch; tüm user-facing string'ler key'li
- **A1:** `includes/i18n.php` + key extraction (tüm PHP dosyalarında hardcoded string tarama → key'e çevir). Session'da dil tercihi.
- **A2:** Dil switch UI + her iki dilde sayfa rendering (admin + frontend). `grep -rnE "echo '[A-Za-z0-9şğıöüçİĞÜÖÇ ]{5,}'" --include="*.php"` ≈ 0 (kalan hardcoded'lar gerekçeli).
- **A3:** Kaç string translate edildi sayısı; kayıp key'ler (`{{ missing }}` output'u görünmemeli).
- **A4:** Readme/documentation her iki dilde mi, sadece EN mi.

### P2-16 — PDF export (dompdf)
- **DoD:** Admin → raporlar → "PDF indir" butonu çalışır (fatura + günlük/aylık rapor)
- **A1:** `composer require dompdf/dompdf`. `src/Services/PdfService.php` + template HTML (`storage/views/pdf/*.html`). Admin'de endpoint + buton.
- **A2:** Örnek rapor üret → PDF dosyası açılıyor + TR karakter doğru + kenarlık düzgün.
- **A3:** dompdf lisansı (LGPL 2.1) CodeCanyon uyumlu mu? (Evet, LGPL kullanılabilir). PDF dosya boyutu makul (≤ 500 KB rapor).
- **A4:** CREDITS.txt güncellenmeli.

### P2-17 — Excel/CSV export (PHPSpreadsheet)
- **DoD:** `admin/raporlar.php` + `admin/siparisler.php`'de "CSV" ve "Excel" butonları
- **A1:** `composer require phpoffice/phpspreadsheet`. Service + endpoint.
- **A2:** Export → dosya download → Excel'de aç, veri doğru, Türkçe karakter bozulmadı (UTF-8 BOM).
- **A3:** Büyük dataset (10k satır) timeout / memory sorunu var mı? Streaming writer?
- **A4:** CREDITS.txt güncelleme.



### P2-19 — Email template editor UI
- **DoD:** `admin/ayarlar/mail-templates.php` — template listesi + WYSIWYG + preview + test gönderim
- **A1:** Template'ler `storage/views/emails/*.html` + meta `storage/views/emails/meta.json` (subject, variables list). Admin'de CodeMirror veya TinyMCE Community. XSS: HTMLPurifier ile filter (P3-22 ile birleşir).
- **A2:** Template edit → save → test email → HTML + text variant doğru render.
- **A3:** Preview gerçek sipariş datasıyla mı, stub mı? Template injection (`{{ system('rm -rf') }}` gibi) engellenmiş mi (`{{ }}` sadece safe escape olmalı)?
- **A4:** Default template'ler "reset to default" butonu.

---

## P3 — Kalite Parlatma

### P3-20 — `includes/` legacy dosyalara `declare(strict_types=1)`
- **DoD:** `grep -L "declare(strict_types=1)" includes/*.php` boş
- **A1:** 6 dosyaya ekle; varsa type hinting uyumsuzluklarını düzelt.
- **A2:** PHPUnit 181/181 hala pass, PHPStan L5 0 error, PHPCS temiz.
- **A3:** Her dosyaya eklerken breaking change oldu mu (int/string mix)? Full test suite + manuel admin smoke.
- **A4:** `.php-cs-fixer.dist.php:122` `declare_strict_types=false` artık kaldırılabilir mi (auto-apply enabled)?

### P3-21 — Empty state standart template
- **DoD:** Tüm admin listing sayfalarında 0 kayıt durumunda aynı komponent görünür
- **A1:** `views/admin/_partials/empty-state.php` (icon + title + description + CTA). Mevcut modüller: products, categories, orders, customers, messages, calendar, activity-log. Her birine include.
- **A2:** Her modülde 0 kayıt simulation → empty state doğru görünür; CTA link çalışıyor.
- **A3:** Responsive mobile, dark mode uyumlu mu?
- **A4:** i18n ile uyumlu (P2-15 bağımlı değil ama key'leri uyumlu olmalı).

### P3-22 — SVG upload XSS taraması
- **DoD:** SVG upload'da `<script>`, `<foreignObject>`, event handler attribute'leri engellenir
- **A1:** `includes/SvgSanitizer.php` — DOMDocument ile parse, whitelist tag/attribute. Upload handler'da SVG ise sanitize sonra kaydet.
- **A2:** Malicious SVG örneği (`<svg><script>alert(1)</script></svg>`) upload → reddedilir VEYA script tag'i strip'li kaydedilir. Permission test: sanitize edilmiş dosya serve edildiğinde script çalışmaz.
- **A3:** Sanitizer bypass testleri (SVG + XML entity, base64 href="javascript:", xlink:href). En az 5 attack vector denenmiş.
- **A4:** Mevcut yüklü SVG'ler için migration scan + re-sanitize.

---

## Yürütme Kuralları

### Paralellik Matrisi

| Grup | Paket ID'leri | Paralel çalışabilir mi? |
|---|---|---|
| A | P0-01 | Tek başına (destructive, kullanıcı onayı) |
| B | P0-02, P0-03, P0-07 | Evet (bağımsız dosyalar) |
| C | P0-04, P0-06, P0-08 | Evet (üretim işleri) |
| D | P0-05 | Hayır — B ve C bittikten sonra |
| E | P1-09..P1-14 | Evet (çoğunlukla bağımsız) |
| F | P2-15..P2-19 | Evet |
| G | P3-20..P3-22 | Evet |

### İteratif Loop Kuralları

1. **Her paketin ilk turunda A1 → A2 → A3 → A4 zinciri tam koşar**, kısa devre yok.
2. **A2/A3/A4 fail ederse:** paket "reopened" olur, A1'e fail notu + somut fix listesi ile döner. Maks 3 iterasyon.
3. **A4 sadece ≥3 paket kapalı olduğunda toplu review yapar** (batch), tek paket için çalışmaz.
4. **Regresyon kriteri (A4):** `vendor/bin/phpunit` 181/181 pass **zorunlu**, PHPStan L5 0 error **zorunlu**, PHPCS error sayısı regresyon yok **zorunlu**.
5. **Dokümantasyon senkron kuralı:** Kod değişikliği bir doc'u (CLAUDE.md, ONBOARDING.md, changelog) güncelliyorsa A1 aynı commit'te günceller, aksi halde A4 reddeder.

### Communication Protocol (agent → agent)

Her el-ele vermede standart mesaj formatı:

```
FROM: A<n>  TO: A<m>
PACKAGE: P0-03
STATUS: claim-done | pass | fail | escalate
ITERATION: 1 | 2 | 3
EVIDENCE:
  - <grep/komut/dosya referansı>
  - ...
NOTES:
  - <kısa açıklama>
NEXT_ACTION:
  - <bir sonraki agent için talimat>
```

### Escalation

3. iterasyonda hala fail varsa:
- A4 "escalate" etiketi ile kullanıcıya raporlar
- Scope daraltma / yaklaşım değiştirme / pakete yardım eklenme seçenekleri sunulur

---

## Tahmini Süre

| Öncelik | Paket sayısı | Tahmini süre (tek geliştirici) | Paralel agent ile |
|---|---|---|---|
| P0 | 8 | 5-8 iş günü | ~3 iş günü (B+C paralel) |
| P1 | 6 | 3-5 iş günü | ~2 iş günü |
| P2 | 5 | 5-8 iş günü | ~3 iş günü |
| P3 | 3 | 1-2 iş günü | ~1 iş günü |

**Toplam:** 14-23 iş günü / paralel 9-12 iş günü.

---

## Başlangıç İçin Öneri

1. **Önce P0-01** (kullanıcı onayı ile — destructive).
2. **Paralel B grubu:** P0-02, P0-03, P0-07 — üç A1 aynı anda çalışır.
3. **Paralel C grubu:** P0-04, P0-06, P0-08 — asset/content üretimi.
4. **Sonra P0-05** (paketleme — B+C çıktılarını birleştirir).
5. **A4 batch review** (P0 toplu).
6. Onaydan sonra P1 grubu paralel, vs.
