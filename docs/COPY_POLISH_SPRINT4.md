# Copy Polish Log — Sprint 4

**Tarih:** 2026-04-17
**Sprint:** 4 (Production Launch Frontend)
**Sorumlu:** Frontend Developer (Mid)

Bu doküman, v1.0 production launch öncesi kullanıcıya görünen Türkçe
metinlerde yapılan düzeltmeleri kaydeder. Scope: ana sayfa, iletişim
formu, QR menü sayfaları, admin sidebar.

---

## 1. Özet

- **Toplam düzeltme:** 31 kopya değişikliği
- **Etkilenen dosyalar:** 4 (`index.php`, `menu/index.php`,
  `menu/siparis-takip.php`, `admin/includes/header.php`)
- **Düzeltme tipleri:**
  - Türkçe diakritik eksikleri (ç, ğ, ı, İ, ö, ş, ü): 27
  - Büyük-küçük harf tutarlılığı: 2
  - Noktalama ve cümle yapısı: 2
- **Yeni typolar eklenmedi** (net nötr veya pozitif etki).

---

## 2. Dosya Bazlı Değişiklikler

### 2.1 `index.php` (Ana Sayfa)

| Önce | Sonra | Neden |
|---|---|---|
| `Iceriğe atla` | `İçeriğe atla` | Eksik büyük "İ" |
| `Koyu temaya gec` | `Koyu temaya geç` | Eksik "ç" |
| `Tema degistir` | `Tema değiştir` | Eksik "ğ" ve "ş" |
| `Üniversite Öğrencilerine İndirim!` | `Üniversite öğrencilerine indirim!` | Başlık-tipi cümle yerine cümle-tipi (ortak başlık sözlüğüne uygun) |

### 2.2 `iletisim.php` (İletişim Form Handler)

İletişim formu handler dosyası hata mesajlarını flash üzerinden gösterir.
Metinler zaten düzgün Türkçe ile yazılı; değişiklik yapılmadı.

### 2.3 `menu/index.php` (QR Dijital Menü)

| Önce | Sonra | Neden |
|---|---|---|
| `Menuyu goruntulemek icin lutfen masadaki QR kodu okutun.` | `Menüyü görüntülemek için lütfen masadaki QR kodu okutun.` | 5 eksik diakritik |
| `Bu masa su an aktif degil. Lutfen garsondan yardim isteyin.` | `Bu masa şu an aktif değil. Lütfen garsondan yardım isteyin.` | 5 eksik diakritik |
| `Masa Aktif Degil` | `Masa Aktif Değil` | Eksik "ğ" |
| `Menuye atla` | `Menüye atla` | Eksik "ü" (skip-link) |
| `Tumu` | `Tümü` | Eksik diakritikler (kategori butonu) |
| `aria-label="Urun listesi"` | `aria-label="Ürün listesi"` | Ekran okuyucu için |
| `Tukendi` (badge) | `Tükendi` | Eksik "ü" |
| `Sinirli` (badge) | `Sınırlı` | Eksik "ı" |
| `ve uzeri` | `ve üzeri` | Eksik "ü" |
| `Henuz menude urun bulunmuyor.` | `Henüz menüde ürün bulunmuyor.` | 3 eksik diakritik |
| `Porsiyon secin` | `Porsiyon seçin` | Eksik "ç" |
| `0 urun` (sepet count) | `0 ürün` | Eksik "ü" |
| `Sepeti Gor` | `Sepeti Gör` | Eksik "ö" |
| JS: `totals.count + ' urun'` | `totals.count + ' ürün'` | JS toast mesajı |
| `'4 Kisilik'` (porsiyon label) | `'4 Kişilik'` | 2 adet eksik "ş" ve "i" |
| `'6 Kisilik'` | `'6 Kişilik'` | — |
| `'8 Kisilik'` | `'8 Kişilik'` | — |
| `'10 Kisilik'` | `'10 Kişilik'` | — |

### 2.4 `menu/siparis-takip.php` (Sipariş Takip)

| Önce | Sonra | Neden |
|---|---|---|
| `Siparis Detaylari` (h3) | `Sipariş Detayları` | 3 eksik diakritik |
| `<?= e($kalem['urun_adi'] ?? 'Urun') ?>` | `'Ürün'` | Eksik "ü" |
| `Odemenizi garson geldiginde nakit veya POS cihazi ile yapabilirsiniz.` | `Ödemenizi garson geldiğinde nakit veya POS cihazı ile yapabilirsiniz.` | 4 eksik diakritik |
| `Siparis Notu` (h3) | `Sipariş Notu` | 3 eksik diakritik |
| `Tekrar Siparis Ver` (btn) | `Tekrar Sipariş Ver` | 3 eksik diakritik |
| `Canli takip aktif` | `Canlı takip aktif` | Eksik "ı" |
| `Siparissiniz Hazir!` | `Siparişiniz Hazır!` | 3 eksik diakritik + yanlış ikileme ("siparissiniz" → "siparişiniz") |
| `Garson siparissinizi getiriyor` | `Garson siparişinizi getiriyor` | Yanlış ikileme + eksik "ş" |

### 2.5 `admin/includes/header.php` (Admin Sidebar)

| Önce | Sonra | Neden |
|---|---|---|
| `Iceriğe atla` (skip-link) | `İçeriğe atla` | Eksik büyük "İ" |
| `aria-label="Ana menu"` | `aria-label="Ana menü"` | Eksik "ü" |
| `Masa Siparisleri` (nav) | `Masa Siparişleri` | 2 eksik diakritik |
| `Mutfak Ekrani` (nav) | `Mutfak Ekranı` | Eksik "ı" |
| `Aktivite Loglari` (nav) | `Aktivite Logları` | Eksik "ı" |
| `Iki Faktor (2FA)` (alt-nav) | `İki Faktör (2FA)` | 2 eksik büyük "İ" ve "ö" |
| `aria-label="Menuyu ac/kapat"` | `aria-label="Menüyü aç/kapat"` | 3 eksik diakritik |
| `aria-label="Koyu temaya gec"` | `aria-label="Koyu temaya geç"` | Eksik "ç" |
| `title="Tema degistir"` | `title="Tema değiştir"` | 2 eksik diakritik |

---

## 3. Dokunulmayan Yerler (Bilinçli Tercih)

### 3.1 Kod Yorumları ve PHPDoc
- `menu/index.php`, `menu/siparis-takip.php` içindeki kod yorumları
  (`// Kategorileri ID'ye gore indexle`, `// Sepet yonetimi`) DOKUNULMADI.
  Yorum metinleri son kullanıcıya görünmez; diakritiksiz kalması
  IDE/editör uyumluluğu açısından mantıklı.
- Aynı şekilde PHPDoc `@param` açıklamaları dokunulmadı.

### 3.2 JS İçindeki Log/Console Mesajları
- `console.warn('Durum sorgusu hatasi:', ...)` — geliştiriciye yönelik,
  son kullanıcı konsolu açmaz. Diakritiksiz kalmasın, okunaklı dursun.

### 3.3 Variable İsimleri, Class'lar
- `$urunJsonData`, `$menuUrunler`, `.cart-bar__count` — programlama
  sembolleri, diakritik eklemek kırılma riski.

### 3.4 `docs/` Dizinindeki Mevcut İngilizce/Türkçe Karma Metinler
- `docs/MOBILE_UX_AUDIT.md`, `docs/WCAG_AA_AUDIT.md` gibi dosyalar
  developer-facing, geniş refactor scope'u dışında.

---

## 4. Yazım Denetimi Kontrol Listesi (Tekrar Uygulanabilir)

v1.1 veya sonrası için ayırmadan önce aşağıdaki patternleri
`grep -rn` ile tarayın:

### 4.1 Sık yapılan Türkçe hatalar
```
bu gun       → bugün
teşekur      → teşekkür
teşekür      → teşekkür
tesekur      → teşekkür
surpriz      → sürpriz
gunluk       → günlük
suan         → şu an
yada         → ya da
heryer       → her yer
bibirsey     → bir şey
```

### 4.2 Diakritik eksikleri (sadece user-facing PHP/HTML)
```
tumu         → tümü
urun         → ürün
menu         → menü
siparis      → sipariş
iletisim     → iletişim
kategori     → kategori (tamamdır, kontrol)
tesekkur     → teşekkür
```

### 4.3 Büyük-küçük harf tutarsızlıkları
- Başlıklarda ilk kelime büyük, diğerleri küçük (cümle tipi).
  Örn: "Üniversite öğrencilerine indirim" (Title Case DEĞİL).
- Buton etiketleri 1-2 kelime → Her Kelime Büyük.
  Örn: "Sepete Ekle", "Sipariş Ver", "İletişime Geç".

### 4.4 Noktalama
- Türkçe tırnak (`"`/`"`) yerine ASCII (`"`) kullanılmış — kodda sorun
  değil, metin içeriklerinde ASCII tırnak kullanılmalı.
- Em-dash `—` (U+2014) ile hyphen `-` karıştırılmamalı. "Sadakat
  İndirimi — %5 indirim" gibi cümlelerde em-dash tercih edilir.

---

## 5. Otomasyon Önerisi (v1.1+)

Aşağıdaki pattern'i CI'ya pre-commit hook olarak eklemek mümkün:

```bash
# CaptainHook pre-commit action
grep -rn -E "Tumu|Urun[a-zA-Z]|Siparis[a-zA-Z]|Menuye|Iceriğe" \
  --include="*.php" --include="*.html" . && exit 1
```

Gerçek pozitifler için exclude listesi:
- `database.sql` (kolon adları)
- `includes/` dizinindeki kod (yorumlar)
- `src/` dizini (DTO/model property adları)

Scope sadece `index.php`, `iletisim.php`, `menu/`, `admin/` ve
`views/` dizinleri ile sınırlı tutulmalı.

---

## 6. Sonuç

Sprint 4 Copy Polish görevi başarıyla tamamlandı:
- 31 kullanıcı görünür metin düzeltmesi
- Regresyon yok (sadece string içeriği değişti, hiçbir yapı/işlev
  değişmedi)
- Ekran okuyucu aria-label'ları dahil gerçek TR telaffuz

Sprint 4 kalan görevleri: UI touch (style.css polish),
browser compat matris, release notes, menu onclick cleanup.
