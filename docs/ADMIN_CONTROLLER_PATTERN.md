# Admin Controller + View Pattern

> Sprint 2 — Tech Lead tarafından `admin/urunler.php` pilot refactor'u ile kurumsallaştırılmıştır.
> Tüm yeni ve mevcut admin sayfaları bu pattern'e göre yazılmalı/refactor edilmelidir.

## 1. Motivasyon

Eski admin sayfaları (`admin/urunler.php` ≈ 350 satır, `admin/kategoriler.php`, `admin/takvim.php` vs.)
tek bir dosyada **auth + permission check + POST handler + SQL + HTML + JS** barındırıyordu.
Bu:

- Test edilemez (logic HTML'e gömülü),
- Okunması zor (sayfanın 3 farklı sorumluluğu iç içe),
- Tekrara yol açar (her sayfa kendi CSRF + flash + redirect boilerplate'ini yazar),
- Review + diff gürültüsü: basit bir label değişikliği 400 satırlık dosyayı touch'lar.

Pattern hedefi: her admin sayfasını **ince router (≈30 satır) + Controller (action'lar) + View (saf HTML)**
üçlüsüne ayırmak; test yazımını, CSP uyumluluğunu ve permission akışını standartlaştırmak.

## 2. Klasör Yapısı

```
admin/
  urunler.php                              ← router (30 satır, bootstrap + auth + dispatch)
  urun-ekle.php / urun-duzenle.php         ← henüz ayrı (Sprint 3'te taşınacak)
  kategoriler.php, takvim.php, ...         ← Sprint 3+'da aynı pattern'e geçecek
src/
  Controllers/
    BaseController.php                     ← ortak: view(), redirect(), flash(), requireCsrf(), requirePermission(), json()
    Admin/
      UrunController.php                   ← index / store / update / destroy / toggleAktiflik / handle
      (Sprint 3+) KategoriController.php, TakvimController.php, ...
  Services/                                ← UrunService, KategoriService, ...  (iş mantığı, Controller buradan çağırır)
  Repositories/                            ← DB katmanı
views/
  admin/
    urunler/
      index.php                            ← ana liste view (saf HTML + echo, logic yasak)
      _row.php                             ← tablo satırı partial (foreach içinde include)
      _delete-modal.php                    ← silme onay modalı partial
    (Sprint 3+) kategoriler/, takvim/, ...
```

## 3. Controller-View-Partial Akışı

```
HTTP Request
      │
      ▼
admin/urunler.php  ← router
      │
      ├─ require bootstrap.php   (Composer autoload + config + DB + helpers)
      ├─ require auth.php + requireLogin()
      ├─ require_permission('product.view')
      │
      ├─ new UrunController()
      │
      ├─ GET  → header.php + $controller->handle() → index() → $this->view('admin.urunler.index', [...]) → footer.php
      └─ POST → $controller->handle() → dispatch → action (requireCsrf + requirePermission + Service call + flash + redirect)
                                                                                     │
                                                                                     └─ exit (header.php YÜKLENMEZ)
```

**Kritik detay — POST'ta header/footer include edilmez.** Çünkü action her zaman `redirect()` ile sonlanır;
HTML render etmek gereksiz DB/memory yüküdür ve "headers already sent" hatasına yol açabilir.

## 4. BaseController API

`src/Controllers/BaseController.php` tüm controller'ların base sınıfıdır.

| Method | Amaç | Örnek |
| --- | --- | --- |
| `view($name, $data)` | `views/<dot-path>.php` include eder | `$this->view('admin.urunler.index', ['products' => $p])` |
| `partial($name, $data)` | View içinde partial include için alias | `$this->partial('admin.urunler._row')` |
| `redirect($url, $status=302)` | Header + exit | `$this->redirect('urunler.php?islem=silindi')` |
| `flash($type, $msg)` | Session flash mesaj (helpers.php `setFlash()` wrapper) | `$this->flash('success', 'Kaydedildi.')` |
| `requireCsrf($redirectOnFail)` | POST CSRF doğrula; başarısızsa flash + redirect | `$this->requireCsrf('urunler.php')` |
| `requirePermission($perm)` | RBAC kontrolü (yetki yoksa 403 sayfası + exit) | `$this->requirePermission('product.update')` |
| `json($data, $status=200)` | JSON response + exit (API action'ları için) | `$this->json(['ok' => true])` |
| `redirect($url)` | Location header + exit | `$this->redirect('urunler.php')` |
| `pagination($def=20, $max=100)` | `?page=&limit=` parse | `['page','limit','offset'] = $this->pagination()` |

## 5. Flash Mesaj + Redirect Pattern

Her POST action şu sıralamaya uymalıdır:

```php
public function destroy(): void
{
    $this->requirePermission('product.delete');      // 1. Yetki
    $this->requireCsrf(self::LIST_URL);               // 2. CSRF
                                                      // 3. Input validation (ID vs)
    $id = (int)($_POST['delete_id'] ?? 0);
    if ($id <= 0) {
        $this->redirect(self::LIST_URL);
    }

    try {                                             // 4. Service çağrısı (try/catch)
        $this->urunService->delete($id);
        $this->flash('success', 'Ürün silindi.');     // 5. Flash (başarı/hata)
        $this->redirect(self::LIST_URL . '?islem=silindi');   // 6. Redirect (Post-Redirect-Get)
    } catch (\Throwable $e) {
        $this->flash('error', 'Silinemedi: ' . $e->getMessage());
        $this->redirect(self::LIST_URL);
    }
}
```

**`?islem=silindi|guncellendi`** query param'ı view tarafından Toast'a dönüştürülür (modern UX).

## 6. Örnek CRUD Anatomisi

### 6.1 `index()` — Liste

```php
public function index(): void
{
    $this->requirePermission('product.view');

    $products      = $this->urunService->getAllWithCategory();
    $allCategories = getCategories();
    $activeCount   = count(array_filter($products, fn($p) => $p['aktif'] == 1));

    $this->view('admin.urunler.index', [
        'products'      => $products,
        'allCategories' => $allCategories,
        'activeCount'   => $activeCount,
        'inactiveCount' => count($products) - $activeCount,
    ]);
}
```

### 6.2 `store()` — Yeni Kayıt (POST)

```php
public function store(): void
{
    $this->requirePermission('product.create');
    $this->requireCsrf(self::LIST_URL);

    $data = [
        'isim'        => trim((string)($_POST['isim'] ?? '')),
        'fiyat'       => (float)($_POST['fiyat'] ?? 0),
        'kategori_id' => (int)($_POST['kategori_id'] ?? 0),
    ];

    try {
        $this->urunService->create($data);
        $this->flash('success', 'Ürün eklendi.');
    } catch (\Throwable $e) {
        $this->flash('error', 'Hata: ' . $e->getMessage());
    }
    $this->redirect(self::LIST_URL);
}
```

### 6.3 `update()` — Güncelleme (POST)

Ayrı "edit" sayfası varsa GET `edit()` + POST `update()` ikilisi; tek sayfalı modal ise sadece `update()`.
Service `update($id, $data)` çağrısı.

### 6.4 `destroy()` — Silme (POST)

Yukarıdaki "Flash + Redirect Pattern" bölümüne bakın.

### 6.5 `handle()` — Router Entry Point

```php
public function handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;
        if ($action === 'store')  { $this->store();  return; }
        if ($action === 'update') { $this->update(); return; }
        if (!empty($_POST['delete_id']))  { $this->destroy();        return; }
        if (!empty($_POST['toggle_id']))  { $this->toggleAktiflik(); return; }
        $this->redirect(self::LIST_URL);
    }
    $this->index();
}
```

## 7. Yeni Admin Sayfası Eklenirken — 5 Adımlı Checklist

Diyelim ki `admin/rezervasyonlar.php` eklemek istiyorsunuz:

1. **Controller oluştur** — `src/Controllers/Admin/RezervasyonController.php`
   - `extends BaseController`, `index() / store() / update() / destroy() / handle()` method'ları
   - Service dependency (`new RezervasyonService()`) constructor'da inject edilsin (test için optional)

2. **View + partial'ları oluştur** — `views/admin/rezervasyonlar/index.php` (+ `_row.php`, `_form-modal.php` vb.)
   - Logic **yasak** — sadece `<?= ?>` echo, `foreach`, `if`. SQL / Service çağrısı view'de olmaz.
   - Tüm event binding: `data-action="..."` + nonce'lı `<script>` delegation (CSP uyumlu, **`onclick` YASAK**)
   - PHP → JS string: `json_encode($x, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)`

3. **Router dosyasını ince tut** — `admin/rezervasyonlar.php` (≈ 25-35 satır)

   ```php
   <?php
   require_once __DIR__ . '/../includes/bootstrap.php';
   require_once __DIR__ . '/includes/auth.php';
   requireLogin();
   require_permission('rezervasyon.view');

   $controller = new \Pastane\Controllers\Admin\RezervasyonController();

   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       $controller->handle();
       exit;
   }

   require_once __DIR__ . '/includes/header.php';
   $controller->handle();
   require_once __DIR__ . '/includes/footer.php';
   ```

4. **Permission tanımla** — `config/permissions.php`'e gerekli permission'ları ekle
   (`rezervasyon.view`, `rezervasyon.create`, `rezervasyon.update`, `rezervasyon.delete`),
   ilgili rol'lere ata. (Bkz. RBAC notları CLAUDE.md)

5. **Nav + test** — `admin/includes/header.php` sidebar'a link ekle, `tests/Unit/Controllers/Admin/`'e
   controller test dosyası yaz (bkz. §9). DB şema uyumluluğunu `DESCRIBE rezervasyonlar` ile doğrula
   (kolon adı konvansiyonu: Türkçe — `isim`, `musteri_adi`, `adet` vs.).

## 8. CSP Uyumluluğu

Tüm admin sayfalarında inline `onclick`, `onchange`, `onsubmit` **YASAK** (Content Security Policy engeller).

**Doğru:**

```html
<button data-action="delete-product" data-id="<?= (int)$product['id'] ?>">Sil</button>

<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';
    document.addEventListener('click', function(e) {
        var t = e.target.closest('[data-action]');
        if (!t) return;
        if (t.dataset.action === 'delete-product') {
            /* ... */
        }
    });
})();
</script>
```

Detaylı rehber: [`docs/CSP_EVENT_PATTERN.md`](./CSP_EVENT_PATTERN.md)

## 9. Controller Test Pattern'i

```php
<?php
// tests/Unit/Controllers/Admin/UrunControllerTest.php
namespace Pastane\Tests\Unit\Controllers\Admin;

use PHPUnit\Framework\TestCase;
use Pastane\Controllers\Admin\UrunController;
use Pastane\Services\UrunService;

class UrunControllerTest extends TestCase
{
    public function test_destroy_calls_service_and_redirects(): void
    {
        $service = $this->createMock(UrunService::class);
        $service->method('find')->willReturn(['id' => 42, 'gorsel' => null]);
        $service->expects($this->once())
                ->method('delete')
                ->with(42)
                ->willReturn(true);

        $_POST = ['delete_id' => '42', 'csrf_token' => 'valid'];
        $_SESSION['csrf_token'] = 'valid';
        $_SESSION['csrf_token_time'] = time();

        // Controller redirect exit'ine düşmeden önce service çağrısını assert et
        // (process isolation gerekebilir — redirect exit'i process'i sonlandırır)
        $controller = new UrunController($service);
        try {
            $controller->destroy();
        } catch (\Throwable) {
            /* redirect exit fallback */
        }
    }
}
```

**Pratik not:** Controller action'ları `redirect()` → `exit` çağırır; test ederken
ya `@runInSeparateProcess` kullanın ya da controller'a dependency-injection yolu ile
bir "redirector" interface geçirin (V2'de düşünülebilir). Sprint 2 kapsamında
**service çağrısının yapıldığını** mock ile doğrulamak yeterlidir.

## 10. Reviewer Checklist (Merge Öncesi Bakılacaklar)

PR reviewer bu checklist'i tek tek işaretlemeli — eksik varsa PR bloke edilir:

- [ ] **Router dosyası ince** (≤ 40 satır). Sadece bootstrap + auth + permission + dispatch.
- [ ] **Controller** `BaseController`'dan extend ediyor, action'lar tipli (`: void`), her biri PHPDoc'lu.
- [ ] **Her POST action** `requirePermission()` + `requireCsrf()` ikilisiyle BAŞLIYOR.
- [ ] **Service çağrıları `try/catch`** içinde; exception → flash('error') + redirect.
- [ ] **Post-Redirect-Get** uygulanmış — action sonunda mutlaka `redirect()`, ASLA HTML render etme.
- [ ] **View dosyası sadece HTML + echo** — içinde SQL, Service çağrısı, `$_POST`/`$_GET` okuma YOK.
- [ ] **Tüm `<?= ?>` çıktıları escape** — `e()`, `(int)`, `(float)` cast'ı veya `json_encode(...)`.
      Ham `<?= $x ?>` YASAK.
- [ ] **CSP uyumu** — inline `onclick`/`onchange` yok; `data-action` + nonce'lı script delegation.
- [ ] **`<script nonce="<?= e(getCspNonce()) ?>">`** kullanılmış — nonce escape edilmiş.
- [ ] **IIFE wrapper** `(function(){ 'use strict'; ... })();` global scope kirliliği önlenmiş.
- [ ] **Partial dosyaları `_` prefix'li** (`_row.php`, `_form-modal.php`) — `include __DIR__ . '/_row.php'` pattern'i.
- [ ] **DB kolon adları doğrulandı** — `DESCRIBE tablo` çıktısıyla birebir eşleşiyor
      (konvansiyon: `isim`, `musteri_adi`, `adet`, `tamamlandi` vb., `ad` / `durum` / `updated_at` **değil**).
- [ ] **PHP lint temiz** — `php -l` her yeni/değişen dosyada `No syntax errors detected` döner.
- [ ] **Smoke test** — sayfa logged-in session ile render edilebildi, POST action'ı doğru redirect verdi.
- [ ] **CLAUDE.md dersine eklendi** (yeni bir edge case / regression öğrenildiyse).

## 11. Pilot Sonuçları — admin/urunler.php

| Metrik | Önce | Sonra |
| --- | --- | --- |
| Router satır sayısı | 350 | 31 |
| Controller | yok | `Admin\UrunController` (6 public action) |
| View | HTML PHP içinde | `views/admin/urunler/index.php` + 2 partial |
| Test edilebilirlik | düşük (her şey inline) | yüksek (service mock'lanabilir) |
| CSP uyumu | zaten OK (Sprint 1) | korundu |
| Davranış değişikliği | — | **sıfır** (URL yapısı, UI, form submit aynı) |

## 12. Sprint 3+ Yol Haritası

- `admin/kategoriler.php` → `Admin\KategoriController`
- `admin/takvim.php` → `Admin\TakvimController`
- `admin/urun-ekle.php` + `urun-duzenle.php` → `UrunController::create()` + `edit()` + `update()` form sayfalarını da buraya taşı
- `admin/mesajlar.php`, `admin/raporlar.php` vb.
- Her PR için yukarıdaki reviewer checklist kullanılmalı.

---

**Soru / öneriler:** Tech Lead'e PR review'de belirt, pattern evrimleşebilir ama dokümana önce ekle.
