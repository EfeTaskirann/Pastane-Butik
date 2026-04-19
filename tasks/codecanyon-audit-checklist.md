# CodeCanyon PHP Script Audit Checklist

> **Amaç:** Envato CodeCanyon'a PHP script (özellikle e-ticaret / dashboard / yönetim paneli) göndermeden önce hard-reject ihtimalini minimize eden tam denetim listesi.
> **Hedef:** Tüm kritik maddeler ✅ olmadan submit etme.
> **Öncelik sırası:** 🔴 Kritik (hard-reject sebebi) → 🟡 Önemli (soft-reject veya düşük puan) → 🟢 Kalite artırıcı

---

## 1. 🔴 SECURITY (En çok red sebebi burada)

### 1.1 SQL Injection
- [ ] Tüm DB sorguları **prepared statement** (PDO `prepare()` + `bindParam()` veya `mysqli_prepare()`) kullanıyor
- [ ] `mysql_*` fonksiyonları **hiç yok** (deprecated, instant reject)
- [ ] Dinamik tablo/kolon adı kullanılan yerlerde **whitelist** var
- [ ] `ORDER BY`, `LIMIT` gibi prepared statement'a bağlanamayan yerler whitelist ile korunuyor
- [ ] Raw query concatenation yok: `"SELECT * FROM x WHERE id=".$id` → ❌

### 1.2 XSS (Cross-Site Scripting)
- [ ] Tüm output'ta `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` veya template engine escape
- [ ] `echo $_GET[...]` / `echo $_POST[...]` doğrudan kullanımı yok
- [ ] JSON output'larda `json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`
- [ ] `innerHTML` yerine `textContent` (JS tarafı)
- [ ] Rich text / WYSIWYG alanları için HTMLPurifier veya benzeri sanitizer

### 1.3 CSRF (Cross-Site Request Forgery)
- [ ] Tüm POST/PUT/DELETE form'larında CSRF token
- [ ] Token `hash_equals()` ile karşılaştırılıyor (timing attack koruması)
- [ ] Token session'da saklanıyor, her request'te üretilmiyor (veya nonce)
- [ ] AJAX request'lerde header veya body'de token gidiyor

### 1.4 Authentication & Session
- [ ] Şifreler **`password_hash()` + `PASSWORD_BCRYPT` veya `PASSWORD_ARGON2ID`** ile hashleniyor
- [ ] `md5()`, `sha1()` şifre için **kesinlikle yok**
- [ ] `password_verify()` ile kontrol ediliyor (== değil)
- [ ] Login'den sonra `session_regenerate_id(true)` çağrılıyor
- [ ] Session cookie: `httponly`, `secure`, `samesite=Lax/Strict`
- [ ] Remember me token'ı DB'de hash olarak saklanıyor (plaintext değil)
- [ ] Brute force koruması (rate limit, captcha, başarısız login sayacı)
- [ ] Şifre sıfırlama token'ı tek kullanımlık, kısa süreli (max 1 saat), `random_bytes()` ile üretilmiş

### 1.5 File Upload
- [ ] MIME type kontrolü server-side (client-side yeterli değil)
- [ ] Dosya uzantısı whitelist ile kontrol (`.php`, `.phtml`, `.phar`, `.pht` asla)
- [ ] Magic bytes kontrolü (`finfo_file()`)
- [ ] Upload klasörü webroot **dışında** veya `.htaccess` ile PHP execution engellenmiş
- [ ] Dosya adı `uniqid()` veya hash ile yeniden isimlendiriliyor (directory traversal koruması)
- [ ] Dosya boyutu sınırı hem `php.ini` hem kodda kontrol ediliyor
- [ ] Image upload'larda `getimagesize()` ve re-encode (GD/Imagick) ile kötücül içerik temizleme

### 1.6 Authorization
- [ ] Her admin sayfasında role check (sadece login check yetmez)
- [ ] IDOR (Insecure Direct Object Reference) yok: `/order/123` başkasının siparişini göstermez
- [ ] `$user_id == $order->user_id` kontrolü her CRUD'da mevcut

### 1.7 Credentials & Config
- [ ] DB şifresi, API key'leri `.env` dosyasında (veya config.php + .gitignore)
- [ ] Hardcoded credential **hiç yok** (`grep -r "password.*=.*['\"]"` temiz)
- [ ] `.env.example` var, `.env` yok (buyer kendi yazacak)
- [ ] Production'da `display_errors = Off`
- [ ] `.git`, `.env`, `composer.lock` vs. public erişime kapalı (`.htaccess` veya config)

### 1.8 Diğer
- [ ] `eval()`, `exec()`, `system()`, `shell_exec()`, `passthru()` **yok** (varsa neden olduğu belgelenmeli)
- [ ] `unserialize()` kullanıcı input'unda yok (PHP Object Injection riski)
- [ ] `include $_GET['page']` gibi LFI/RFI açıkları yok
- [ ] HTTPS zorunlu (production için header veya kod bazında redirect)
- [ ] Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`, `Referrer-Policy`

---

## 2. 🔴 CODE QUALITY

### 2.1 PHP Standartları
- [ ] **PSR-12** coding style (tek tip indentation, brace placement, spacing)
- [ ] `declare(strict_types=1)` top-level dosyalarda
- [ ] Type hints: parametre ve return type (PHP 7.4+ için minimum)
- [ ] Namespace kullanımı (procedural spagetti değil)
- [ ] Autoloading: Composer PSR-4 (require/include spam yok)

### 2.2 Mimari
- [ ] Minimum MVC veya benzeri separation (PHP + HTML + SQL aynı dosyada değil)
- [ ] DB credentials tek dosyada (`config.php` veya `.env`)
- [ ] Reusable fonksiyonlar / class'lar (copy-paste kod yok)
- [ ] Error handling: try/catch, custom exception'lar, error logging

### 2.3 Temizlik
- [ ] `var_dump()`, `print_r()`, `die()`, `console.log()` **hiç yok**
- [ ] `TODO`, `FIXME`, `HACK` commentleri temizlenmiş (veya gerçekten hak ediyorsa bırakılabilir)
- [ ] Kullanılmayan dosya, fonksiyon, import yok
- [ ] Commented-out kod blokları temizlenmiş
- [ ] Test dosyaları, `.DS_Store`, `Thumbs.db`, `.vscode/`, `.idea/` pakette yok

### 2.4 Error Handling
- [ ] Tüm DB operation'ları try/catch içinde
- [ ] `@` error suppression operatörü yok (sadece gerçekten gerektiği yerde)
- [ ] User'a teknik error mesajı gitmez (stack trace, SQL error vb.)
- [ ] Log dosyası webroot dışında veya erişime kapalı

---

## 3. 🔴 HTML / CSS / FRONTEND

### 3.1 HTML
- [ ] **W3C valid HTML5** (validator.w3.org ile test)
- [ ] Doctype `<!DOCTYPE html>` her sayfada
- [ ] Semantic tags: `<header>`, `<nav>`, `<main>`, `<article>`, `<section>`, `<footer>`
- [ ] Her image'ta `alt` attribute (dekoratif ise `alt=""`)
- [ ] Her form input'ta `<label>` (accessibility)
- [ ] ARIA attributes interaktif elementlerde
- [ ] `<title>` her sayfada benzersiz ve açıklayıcı
- [ ] Meta description, meta viewport var

### 3.2 CSS
- [ ] **Inline CSS YOK** (Envato'nun açık hard-reject sebebi)
- [ ] CSS external file'da (`<style>` tag'ı içinde kritik CSS haricinde yok)
- [ ] W3C valid CSS (vendor prefix'leri hariç)
- [ ] Responsive breakpoints: mobile (320-480), tablet (768), desktop (1024+)
- [ ] `!important` spam yok
- [ ] CSS organize (yorumlarla bölümlenmiş veya BEM/SMACSS yaklaşımı)
- [ ] Font'lar Google Fonts veya self-hosted (lisans uygun)

### 3.3 JavaScript
- [ ] jQuery varsa production CDN veya minified
- [ ] `console.log`, `debugger`, `alert` debug kalıntıları yok
- [ ] `'use strict'`
- [ ] Event handler'lar inline `onclick=""` değil, addEventListener
- [ ] AJAX URL'leri absolute değil, config'den geliyor

### 3.4 Browser Uyumluluğu
- [ ] Chrome, Firefox, Safari, Edge son 2 major version
- [ ] IE11 desteği CodeCanyon için artık zorunlu değil ama açıkça belirtmek iyi
- [ ] Mobile browser'larda (iOS Safari, Chrome Android) test edilmiş

---

## 4. 🔴 PERFORMANCE

- [ ] N+1 query problemi yok (listelerde JOIN veya eager loading)
- [ ] Gereksiz `SELECT *` yok (sadece ihtiyacın olan kolonlar)
- [ ] DB'de index'ler var (en azından foreign key'ler ve sık sorgulanan kolonlar)
- [ ] Image'lar optimize (`.webp` ideal, PNG/JPG compressed)
- [ ] Lazy loading: `<img loading="lazy">`
- [ ] CSS/JS minified versiyon mevcut (kaynak da gönderilecek)
- [ ] Asset'ler için caching header'ları
- [ ] Büyük dosyalar için chunk upload / pagination

---

## 5. 🔴 DOCUMENTATION (Envato'nun özel olarak talep ettiği)

### 5.1 Buyer'a giden paket içinde olması gerekenler
- [ ] `documentation/` klasörü (HTML veya Markdown)
- [ ] **Server requirements** (PHP versiyonu, extension'lar, MySQL versiyonu)
- [ ] **Kurulum adımları** (step-by-step, ekran görüntüleri ile)
- [ ] **Configuration guide** (`.env` ayarları, SMTP, payment gateway)
- [ ] **Admin panel kullanım kılavuzu**
- [ ] **User guide** (varsa frontend kullanıcıları için)
- [ ] **FAQ / Troubleshooting** bölümü
- [ ] **Changelog.md** (versiyon geçmişi)
- [ ] **Credits.txt** (kullandığın tüm 3. parti kütüphaneler ve lisansları)
- [ ] **License.txt** (CodeCanyon Regular/Extended License açıklaması)

### 5.2 Dökümanın kendisi
- [ ] Resimli kurulum rehberi
- [ ] Video tutorial linki (YouTube unlisted) bonus puan
- [ ] İngilizce dilbilgisi kontrollü
- [ ] Kod örneklerinde syntax highlighting

---

## 6. 🔴 LICENSING (En gizli hard-reject sebeplerinden)

### 6.1 Kullanılan her asset'in lisansı uyumlu olmalı
- [ ] Tüm JS library'leri (jQuery, Chart.js, vb.) MIT/Apache/BSD — **GPL yok** (Envato lisansı ile çakışır)
- [ ] Tüm CSS framework'leri (Bootstrap, Tailwind) uygun lisansta
- [ ] Font'lar: Google Fonts, Font Awesome Free, veya satın alınmış lisanslı
- [ ] Icon setleri: free-for-commercial use veya satın alınmış
- [ ] Stock image'lar: Unsplash, Pexels, Pixabay veya satın alınmış (lisans belgesi)
- [ ] Demo content'te marka logosu, telif hakkı ihlali yok
- [ ] `credits.txt` içinde her library adı, versiyon, lisans, kaynak URL

### 6.2 Kendi kodun
- [ ] Başka bir kaynaktan kopyalanmış kod yok (GitHub, StackOverflow copy-paste)
- [ ] Başka bir CodeCanyon item'dan alıntı yok

---

## 7. 🔴 CODECANYON-ÖZEL GEREKSİNİMLER

### 7.1 Paket yapısı
- [ ] Zip içinde: `main_files/`, `documentation/`, `licensing/`
- [ ] `main_files/` → asıl kurulacak dosyalar
- [ ] Demo database SQL dump dahil
- [ ] Demo content (dummy data) kurulum sırasında opsiyonel
- [ ] Clean install path — buyer kolayca kurabilmeli
- [ ] Uninstall script (DB tablolarını temizleyen)

### 7.2 Ürün sayfası için hazırlık (submission'da istenir)
- [ ] 590×300 thumbnail
- [ ] 80×80 icon
- [ ] Preview image'ları (1370×752, minimum 6 adet)
- [ ] Feature listesi (bullet point)
- [ ] Demo URL (canlı, 7/24 erişilebilir)
- [ ] Admin demo credentials (ama her 1 saatte reset eden mekanizma iyi olur)

### 7.3 Unique Value
- [ ] Mevcut CodeCanyon item'larından **açıkça farklı** özellikler
- [ ] Benzer item'ları araştırdın mı? (similarity → hard reject)
- [ ] "Lack of features" red sebebi: **minimum 5-7 ana özellik** olmalı

### 7.4 Demo ve Preview
- [ ] Demo site responsive (mobile'da düzgün çalışıyor)
- [ ] Demo hızlı (3 sn içinde yükleniyor)
- [ ] Demo'da yazım hataları yok
- [ ] Placeholder image yerine gerçek tasarım içerikleri
- [ ] Profesyonel görünüm (amateur hissi vermiyor)

---

## 8. 🟡 DESIGN QUALITY (Hard-reject'e kadar gidebilir)

- [ ] Premium market için yeterli görsel kalite
- [ ] Consistent spacing, typography, color palette
- [ ] Dark mode destek (bonus)
- [ ] Animation/transition'lar abartısız ama var
- [ ] Empty state'ler tasarlanmış (boş liste, 0 sipariş vb.)
- [ ] Loading state'ler (spinner, skeleton)
- [ ] Error state'ler kullanıcı dostu mesajlarla
- [ ] Bootstrap/Tailwind default look'tan farklılaşmış (custom feel)

---

## 9. 🟡 DATABASE

- [ ] Foreign key constraints var
- [ ] Indexes doğru kolonlarda
- [ ] Charset `utf8mb4` (emoji destekli)
- [ ] Migration veya install script (manuel SQL çalıştırmaya gerek yok)
- [ ] Backup/restore fonksiyonu admin panelde (bonus)

---

## 10. 🟢 BONUS (Puanı yükselten özellikler)

- [ ] Multi-language support (i18n)
- [ ] REST API endpoints (bonus ama "scope" dışına çıkmadan)
- [ ] Email template editor
- [ ] Dashboard istatistikleri (grafik, chart)
- [ ] PDF export (fatura, rapor)
- [ ] Excel/CSV export/import
- [ ] Payment gateway entegrasyonu (Stripe, PayPal, Iyzico)
- [ ] 2FA (two-factor authentication)
- [ ] Activity log (kim ne yaptı)
- [ ] Backup & restore tool
- [ ] White label option (logo değiştirme)

---

## AUDIT ÖNCESİ SON KONTROL

Submit etmeden önce bu komutları çalıştır:

```bash
# 1. Hassas veri taraması
grep -rnE "(password|api_key|secret|token)\s*=\s*['\"][^'\"]+['\"]" --include="*.php" .

# 2. Debug kalıntıları
grep -rnE "(var_dump|print_r|console\.log|alert\(|dd\(|die\()" --include="*.php" --include="*.js" .

# 3. Deprecated fonksiyonlar
grep -rnE "(mysql_query|mysql_connect|ereg|split|each\()" --include="*.php" .

# 4. Güvensiz fonksiyonlar
grep -rnE "(eval|exec|system|shell_exec|passthru)\s*\(" --include="*.php" .

# 5. Raw SQL concatenation (prepared statement kontrolü)
grep -rnE "(SELECT|INSERT|UPDATE|DELETE).*\.\s*\\\$" --include="*.php" .

# 6. XSS riskli echo
grep -rnE "echo\s+\\\$_(GET|POST|REQUEST|COOKIE)" --include="*.php" .

# 7. Gereksiz dosyalar
find . -name ".DS_Store" -o -name "Thumbs.db" -o -name ".vscode" -o -name ".idea" -o -name "node_modules" -o -name ".git"

# 8. Dosya izinleri (Linux)
find . -type f -perm /o+w  # World-writable dosyalar sıkıntı
```

---

## İSTATİSTİK: En çok hard-reject sebepleri (forum analizine göre)

1. **"Quality Standard" (generik)** — %40 — Genelde aşağıdakilerden biridir ama isim verilmez
2. **Design kalitesi yetersiz** — %25 — Bootstrap default görünüm
3. **Similarity** — %15 — Mevcut item'lara çok benzer
4. **Security açıkları** — %10 — Tespit edilirse anında reject
5. **Dokümantasyon eksik** — %5
6. **Feature eksik / çok basit** — %5

---

## Sonuç

Bu checklist'in **🔴 Kritik** bölümündeki tüm kutucuklar ✅ değilse, submit etme. CodeCanyon hard-reject sonrası aynı item'ı yeniden gönderemezsin — major değişikliklerle "new item" olarak göndermek gerekir.
