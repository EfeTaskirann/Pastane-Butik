# CSP Event Binding Pattern

**Proje:** Tatlı Düşler Butik Pastane
**Son güncelleme:** 2026-04-17
**Kapsam:** Admin sayfalarında inline `onclick=""`/`onsubmit=""` kullanımını CSP-uyumlu pattern'e dönüştürme rehberi.

> **Neden?** `includes/security.php` → `setSecurityHeaders()` fonksiyonu `script-src 'self' 'nonce-{$nonce}'` CSP başlığı gönderiyor. `'unsafe-inline'` **kaldırıldı**, dolayısıyla **inline event handler'lar tarayıcı tarafından çalıştırılmaz** (sessizce bloklanır).
>
> Bu doküman pilot temizlik yapılan [admin/urunler.php](../admin/urunler.php) referans alınarak hazırlanmıştır. Aynı pattern'i `kategoriler.php`, `mesajlar.php`, `takvim.php` ve diğer admin sayfalarına uygulayın.

---

## 0. Genel Kural

- **YASAK:** `onclick="..."`, `onsubmit="..."`, `onchange="..."`, `onload="..."`, `onerror="..."` (tüm `on*` inline handler'lar)
- **YASAK:** `href="javascript:..."` link'leri
- **YASAK:** `eval()`, `new Function()`, inline `setTimeout('string', 1000)`
- **ZORUNLU:** Tüm JS kodları `<script nonce="<?= e(getCspNonce()) ?>">` bloğunda olmalı
- **ZORUNLU:** Event binding → `addEventListener` veya **event delegation**

> Nonce fonksiyonu: `getCspNonce()` → `includes/security.php`. Request başına tek nonce üretilir (static cache).

---

## 1. Nonce'lı Script Bloğu Açılışı

Her admin sayfasının **footer'dan önce** bulunan script bloğu şu şablonla başlamalı:

```php
<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';

    // --- DOM referansları ---
    // --- Yardımcı fonksiyonlar ---
    // --- Event binding ---
})();
</script>
```

IIFE (Immediately Invoked Function Expression) sayesinde global scope kirlenmez, `'use strict'` ile sessiz hatalar yakalanır.

---

## 2. Pattern: Basit Buton Tıklaması

### ÖNCE (YANLIŞ — CSP ile bloklanır)

```html
<button onclick="toggleProduct(<?= $product['id'] ?>)">Aktif/Pasif</button>

<script>
function toggleProduct(id) {
    document.getElementById('toggleId').value = id;
    document.getElementById('toggleForm').submit();
}
</script>
```

### SONRA (DOĞRU)

```html
<button type="button"
        data-action="toggle-product"
        data-id="<?= (int)$product['id'] ?>">
    Aktif/Pasif
</button>

<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';

    document.addEventListener('click', function(e) {
        var target = e.target.closest('[data-action="toggle-product"]');
        if (!target) return;

        var id = target.getAttribute('data-id');
        if (!id) return;

        document.getElementById('toggleId').value = id;
        document.getElementById('toggleForm').submit();
    });
})();
</script>
```

**Kritik noktalar:**
- `data-action` = fiil (kebab-case): `toggle-product`, `delete-user`, `open-modal`
- `data-id`, `data-name` vb. PHP'den HTML'e veri geçişi için. **Mutlaka `e()` veya `(int)` cast** et (XSS koruması).
- `event delegation` kullanımı (document üzerinde tek listener) → dinamik olarak eklenen butonlar için de çalışır + performanslı.
- `type="button"` önemli — form içinde kullanılıyorsa default `submit` olmasın.

---

## 3. Pattern: Onay Modalı (Confirm Dialog)

### ÖNCE

```html
<button onclick="if(confirm('Silinsin mi?')) deleteItem(<?= $id ?>)">Sil</button>
```

### SONRA

```html
<button type="button"
        data-action="delete-item"
        data-id="<?= (int)$id ?>"
        data-name="<?= e($item['isim']) ?>">
    Sil
</button>

<!-- Modal overlay (bootstrap/custom) -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3>Silme Onayı</h3>
        <p><strong id="deleteItemName"></strong> silinecek. Emin misiniz?</p>
        <button type="button" data-action="close-delete-modal">İptal</button>
        <button type="button" data-action="confirm-delete">Sil</button>
    </div>
</div>

<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';

    var deleteId   = null;
    var modal      = document.getElementById('deleteModal');
    var nameEl     = document.getElementById('deleteItemName');
    var deleteForm = document.getElementById('deleteForm');
    var deleteInp  = document.getElementById('deleteId');

    function openModal(id, name) {
        deleteId = id;
        nameEl.textContent = name;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        deleteId = null;
    }

    function confirmDelete() {
        if (deleteId === null) return;
        deleteInp.value = String(deleteId);
        deleteForm.submit();
    }

    document.addEventListener('click', function(e) {
        var target = e.target.closest('[data-action]');
        if (!target) return;

        var action = target.getAttribute('data-action');

        if (action === 'delete-item') {
            openModal(target.getAttribute('data-id'), target.getAttribute('data-name') || '');
        } else if (action === 'close-delete-modal') {
            closeModal();
        } else if (action === 'confirm-delete') {
            confirmDelete();
        }
    });

    // Overlay dışı tıklama + ESC
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
```

> **UYARI:** `confirm()`/`alert()` native'leri CSP tarafından bloklanmaz ama UX kötü. Her yerde modal kullan.

---

## 4. Pattern: AJAX Submit (Form)

### ÖNCE

```html
<form onsubmit="submitKategori(event)">
    <input name="isim">
    <button type="submit">Kaydet</button>
</form>

<script>
function submitKategori(e) {
    e.preventDefault();
    fetch('/api/kategori', { method: 'POST', body: new FormData(e.target) });
}
</script>
```

### SONRA

```html
<form id="kategoriForm" data-endpoint="/api/v1/kategoriler">
    <?= csrfTokenField() ?>
    <input name="isim" required>
    <button type="submit">Kaydet</button>
</form>

<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';

    var form = document.getElementById('kategoriForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var endpoint = form.getAttribute('data-endpoint');
        var formData = new FormData(form);

        fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                window.location.reload();
            } else {
                alert(data.message || 'Hata oluştu');
            }
        })
        .catch(function() {
            alert('Ağ hatası');
        });
    });
})();
</script>
```

**Kritik noktalar:**
- `data-endpoint` ile URL PHP'den HTML'e taşınır — JS içine hardcode etme.
- `csrfTokenField()` form içinde — FormData otomatik olarak `csrf_token` alanını alır.
- `X-Requested-With` header → backend'de AJAX tespiti için.

---

## 5. Pattern: Modal Open/Close (Trigger)

### SONRA

```html
<button type="button" data-modal-open="yeni-urun-modal">Yeni Ürün</button>

<div class="modal-overlay" id="yeni-urun-modal" data-modal>
    <div class="modal">
        <button type="button" class="modal-close" data-modal-close>X</button>
        <!-- içerik -->
    </div>
</div>

<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';

    document.addEventListener('click', function(e) {
        var opener = e.target.closest('[data-modal-open]');
        if (opener) {
            var id = opener.getAttribute('data-modal-open');
            var modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            return;
        }

        var closer = e.target.closest('[data-modal-close]');
        if (closer) {
            var openModal = closer.closest('[data-modal]');
            if (openModal) {
                openModal.classList.remove('active');
                document.body.style.overflow = '';
            }
            return;
        }

        // Overlay click
        var overlay = e.target.closest('[data-modal]');
        if (overlay && e.target === overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[data-modal].active').forEach(function(m) {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
    });
})();
</script>
```

> Bu pattern **tek global script** olarak header veya footer'a eklenebilir. Her sayfada tekrar yazmaya gerek yok. **Öneri:** `assets/js/admin-modal.js` olarak çıkar, `admin/includes/footer.php`'de nonce'la yükle.

---

## 6. Pattern: PHP Değişkenini JS'e Güvenli Geçirme

### YASAK

```html
<script>
var user = { name: '<?= $user['name'] ?>' };  // XSS!
var items = <?= e($items) ?>;                  // e() JSON için yanlış!
</script>
```

### DOĞRU

```html
<script nonce="<?= e(getCspNonce()) ?>" id="page-data" type="application/json">
<?= json_encode([
    'user'  => ['name' => $user['name']],
    'items' => $items,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>

<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';
    var dataEl = document.getElementById('page-data');
    if (!dataEl) return;
    var data = JSON.parse(dataEl.textContent);
    // data.user, data.items ...
})();
</script>
```

**Neden?**
- `type="application/json"` → tarayıcı script olarak çalıştırmaz, sadece data container.
- `JSON_HEX_*` flag'leri `<`, `>`, `&`, `'`, `"` karakterlerini unicode escape eder → script context XSS önler.
- `e()` sadece HTML için. JSON context'te **`json_encode`** kullan. (CLAUDE.md dersi!)

---

## 7. Pattern: Dinamik Olarak Eklenen Element'ler

Event delegation **dinamik DOM için de** çalışır:

```js
// document üzerinde dinle → sonradan eklenen butonlar da yakalanır
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action="load-more"]');
    if (!btn) return;
    // ...
});
```

Alternatif (dar scope): Container elemanda dinle (performans için).

```js
var list = document.getElementById('urun-listesi');
list.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action="delete-row"]');
    if (!btn) return;
    // ...
});
```

---

## 8. Checklist — Yeni Sayfa Yazarken

- [ ] `onclick=`, `onsubmit=`, `onchange=`, `onload=` → **0 adet** (grep ile doğrula)
- [ ] Her `<script>` tag'inde `nonce="<?= e(getCspNonce()) ?>"` var mı?
- [ ] `<script src="...">` external script'lerde de nonce var mı? **(Evet, CSP gerektirir)**
- [ ] PHP → JS veri geçişi `json_encode` ile mi?
- [ ] Event'ler `addEventListener` veya event delegation ile mi bağlandı?
- [ ] `href="javascript:..."` var mı? Varsa `<button type="button">` ile değiştir.
- [ ] `type="button"` vs `type="submit"` doğru mu?
- [ ] ARIA: `aria-label`, `aria-hidden`, `data-tooltip` eksiksiz mi?

### Grep komutu (PR'dan önce çalıştır)

```bash
rg -n "onclick=|onsubmit=|onchange=|onload=|onerror=|href=\"javascript:" admin/
# Çıktı: 0 satır olmalı
```

---

## 9. Debug — "Script çalışmıyor" Diyorsanız

1. **Browser DevTools → Console:** `Refused to execute inline script because it violates the following Content Security Policy directive` mesajı var mı?
2. **Network → Headers:** `Content-Security-Policy` header'ında nonce değeri doğru mu?
3. **View Source:** `<script nonce="XXXX">` içindeki nonce ile header'daki nonce **eşit** mi?
4. Nonce eşit değilse: `setSecurityHeaders()` çağrısı header gönderildikten SONRA olmamalı. `bootstrap.php` header'ı erken set etmeli.
5. `getCspNonce()` static cache'li — aynı request içinde hep aynı değeri dönüyor, sorun değil.

---

## 10. Örnek Referans

Çalışan tam örnek: [admin/urunler.php](../admin/urunler.php)

- 5 inline onclick temizlendi
- `data-action` delegation pattern
- Modal + toggle + confirm dialog
- Client-side filter

Bu dosya **referans implementasyondur**. Diğer admin sayfaları bu şablonu takip etmelidir.

---

## 11. Kaynaklar

- [MDN: Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [OWASP: Content Security Policy Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [CSP Evaluator](https://csp-evaluator.withgoogle.com/) — mevcut CSP'yi skorla
- `includes/security.php` → `setSecurityHeaders()` ve `getCspNonce()` implementasyonu
