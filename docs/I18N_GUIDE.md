# I18N Guide — Tatlı Düşler

TR/EN cift dil destegi rehberi. P2-15 paketinin runtime sozlesmesi.

> Olcekleme: yeni bir dil eklenecekse hem `lang/<code>.php` olusturulmali hem de
> `I18n::ALLOWED_LOCALES` whitelist'ine eklenmelidir. **Whitelist disindaki
> locale isimleri sessizce reddedilir** (cookie/session poisoning korumasi).

---

## 1. Mimari ozet

| Bilesen | Yol | Sorumluluk |
|---|---|---|
| `I18n` sinifi | `includes/i18n.php` | Locale tespit/set, dosya yukleme, ceviri lookup, fallback |
| `t()` helper | `includes/i18n.php` | Global ceviri kestirmesi: `t('key', $vars)` |
| `locale()` helper | `includes/i18n.php` | Aktif locale'i dondurur (`'tr'` veya `'en'`) |
| Ceviri dosyalari | `lang/tr.php`, `lang/en.php` | PHP array, nested veya flat dot-key |
| Bootstrap entegrasyonu | `includes/bootstrap.php` | `I18n::load()` her istegin basinda calisir |

`I18n::load()` davranisi:

1. `?lang=tr` veya `?lang=en` GET parametresi varsa locale'i set eder, **ayni
   URL'ye lang param'i cikarilmis halde 302 redirect** yapar.
2. Aktif locale'i tespit eder: session > cookie > default (`tr`).
3. Aktif locale dosyasini lazy-load eder (`static $translations` cache).

---

## 2. Kullanım

### Template'de (ZORUNLU `e()` ile escape)

```php
<h1><?= e(t('home.hero_title')) ?></h1>
<a href="..."><?= e(t('btn.add_to_cart')) ?></a>
<button aria-label="<?= e(t('a11y.toggle_menu')) ?>">...</button>
```

> **Asla** ham `<?= t('key') ?>` yazma. Ceviri stringi user-controllable
> placeholder'lar icerebilir. **`e()` ile her zaman escape et.**

### Placeholder'li ceviri

```php
<p><?= e(t('common.welcome', ['name' => $userName])) ?></p>
<!-- TR: "Hoş geldin, Efe"  |  EN: "Welcome, Efe" -->

<span><?= e(t('order.cart_count', ['count' => count($items)])) ?></span>
<!-- TR: "3 ürün"  |  EN: "3 items" -->
```

Placeholder syntax: **`{{name}}`** — capraz parantez. Placeholder map'inde
yer almayan `{{x}}` placeholder'lari literal olarak kalir (preserve).

### Servis/controller'da (escape gerekmez — PHP scope, JSON output)

```php
return json_response([
    'success' => true,
    'message' => t('success.order_placed'),
]);
```

### JS'e gecirme (script context XSS korumasi)

```php
<script nonce="<?= e(getCspNonce()) ?>">
const messages = {
    addedToCart: <?= json_encode(t('success.added_to_cart'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
    cartEmpty:   <?= json_encode(t('order.cart_empty'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
};
</script>
```

---

## 3. Yeni key ekleme (DRIFT YASAK)

**Kural:** Bir key her iki dil dosyasinda da bulunmali. CI'da
`I18nTest::test_translation_files_have_same_top_level_groups` testi drift'i yakalar.

### Adim 1: Anlamli grupta key tanimla

```php
// lang/tr.php
'btn' => [
    // ...
    'newsletter_subscribe' => 'Bültene Abone Ol',
],
```

```php
// lang/en.php
'btn' => [
    // ...
    'newsletter_subscribe' => 'Subscribe to Newsletter',
],
```

### Adim 2: Test calisitir

```bash
vendor/bin/phpunit tests/Unit/I18nTest.php
```

`test_translation_files_have_same_top_level_groups` ve `test_translation_files_have_minimum_keys` GECMELI.

### Adim 3: Template'de kullan

```php
<button class="btn"><?= e(t('btn.newsletter_subscribe')) ?></button>
```

---

## 4. Mevcut key gruplari

| Grup | Aciklama | Ornek key |
|---|---|---|
| `common.*` | Genel kelimeler (loading, save, cancel) | `common.loading` |
| `nav.*` | Navigasyon (home, cart, login) | `nav.home` |
| `btn.*` | Buton etiketleri | `btn.add_to_cart` |
| `form.*` | Form alan label/placeholder'lari | `form.email` |
| `order.*` | Siparis akisi (cart, status) | `order.your_cart` |
| `admin.*` | Yonetim paneli (sidebar, header) | `admin.dashboard` |
| `error.*` | Hata mesajlari (CSRF, validation) | `error.invalid_email` |
| `success.*` | Basari mesajlari (saved, sent) | `success.order_placed` |
| `home.*` | Ana sayfa ozel metinleri | `home.hero_title` |
| `language.*` | Dil secici label'lari | `language.tr_short` |
| `a11y.*` | Erisilebilirlik aria-label'lari | `a11y.toggle_menu` |

> **Kural:** Yeni grup eklerken iki dil dosyasinda da olusturmayi UNUTMA.

---

## 5. Fallback davranisi

Sira:

1. **Aktif locale**'de key arama (ornek `en/'nav.home'`).
2. Bulamazsa **default locale**'e (`tr`) dusme.
3. Bulamazsa **key string'in kendisini** dondurme (UI'da `nav.home` gozukur).
4. Eksik key Logger'a `warning` seviyesinde yazilir (sadece ilk gozlemde —
   ayni key her cagrida log spam yapmaz).

Bu sayede:
- Eksik ceviriler **gozle gorulebilir** (UI'da debug kolayligi).
- Production'da exception fırlamaz (graceful degrade).
- Log'larda hangi key'lerin eksik oldugu listelenir.

---

## 6. Dil degistirme akisi

### URL'den (en yaygın)

```
https://example.com/menu/?t=ABC&lang=en
   ↓ (302 redirect lang param'i cikarilarak)
https://example.com/menu/?t=ABC
```

Cookie + session'a `en` yazilir, sonraki istekler ayni locale'le servis edilir.

### Programmatik

```php
I18n::setLocale('en');  // session + cookie set, basariliysa true doner
```

### UI switcher (yerlesik)

`includes/header.php` ve `admin/includes/header.php` icinde TR | EN linkleri
hazir. CSS class: `.lang-switcher` (admin.css + style.css'de tanimli).

---

## 7. Yeni dil ekleme

1. `lang/<code>.php` olustur (TR'yi sablon al, ceviri yap).
2. `includes/i18n.php` icinde:
   ```php
   public const ALLOWED_LOCALES = ['tr', 'en', 'de'];  // 'de' ekle
   ```
3. Header'lardaki `lang-switcher` blokuna yeni link ekle.
4. PHPUnit test'i her iki tarafta da gecsin (`I18nTest`).

---

## 8. Test stratejisi

`tests/Unit/I18nTest.php` 17 test:

- Default locale, set/get
- Whitelist disi locale red
- TR/EN round-trip
- Placeholder substitution
- Bilinmeyen key fallback (key literal)
- Bilinmeyen locale fallback (default'a duser)
- Dot-notation lookup
- Drift testi (TR/EN ayni key listesine sahip mi?)
- Min 100 key kontrolu

```bash
vendor/bin/phpunit --testdox tests/Unit/I18nTest.php
```

---

## 9. Pitfall'lar

| Hata | Cozum |
|---|---|
| `<?= t('key') ?>` (escape unutuldu) | Daima `<?= e(t('key')) ?>` |
| TR'de tam diakritiklerle yazmadim | `İ Ş Ğ Ç Ü Ö ı` — eksiksiz yaz |
| Yeni key sadece TR'ye eklendi | EN'e de ekle, drift testi yakalar |
| `setLocale('TR')` calismiyor | Lower-case zorunlu, `'tr'` |
| `?lang=de` ignore ediliyor | Whitelist disinda — `ALLOWED_LOCALES`'a ekle |
| JS'e ceviri tasimak istiyorum | `json_encode(t('key'), JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT \| JSON_UNESCAPED_UNICODE)` |

---

## 10. Lessons (CLAUDE.md'ye eklenecek)

- I18n key'leri **flat dot-notation veya nested array** ikisi de calisir; tutarlilik icin **nested** tercih (gruplar net ayrik).
- Composer files autoload + classmap ayni dosyaya yazilirsa double-include hatasi olur — sadece **files**'da tut.
- TR/EN drift'i yakalamak icin testte iki dosyanin top-level group key'leri ve grup-ici key'leri **birebir esitlenmeli**.
- `t()` ciktisinin escape sorumlulugu CALL-SITE'da — helper kendi escape etmez (raw donmesi gerekiyor cunku JSON/email/SMS context'lerine de gidiyor).
