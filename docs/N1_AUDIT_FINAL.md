# N+1 Query Audit — Final (Sprint 4)

**Tarih:** 2026-04-17
**Sorumlu:** Backend Developer (Sprint 4 — Production Launch)
**Onceki audit:** [`docs/N1_AUDIT_SPRINT1.md`](N1_AUDIT_SPRINT1.md) (mutfak.php + masa-siparisleri.php fix)
**Kapsam:** Admin paneli hot path'leri + AJAX/polling endpoint'leri.

## Ozet Tablo

| Konum | Metot / Senaryo | Onceki | Sonrasi | Durum | Sprint |
|---|---|---:|---:|---|:---:|
| `admin/garson.php` AJAX (30 sn polling) | foreach -> `getSiparisDetay(id)` (2 query/sipariş) | `1 + 2N` | `2` | **FIX** | 4 |
| `admin/garson.php` sayfa ilk yukleme | foreach -> `getSiparisDetay(id)` | `1 + 2N` | `2` | **FIX** | 4 |
| `admin/dashboard.php` son urunler | foreach render, kalem yok | `1` | `1` | OK | — |
| `admin/dashboard.php` son mesajlar | `getAllOrdered()` -> array_slice(5) | `1` | `1` | Optimize onerisi | 5 |
| `admin/raporlar.php` uzerine render | 9 aggregate sorgu (toplam), foreach icinde DB yok | `9` | `9` | OK (cache adayi) | — |
| `admin/raporlar.php` QR menu bloklari | 7 aggregate sorgu, foreach render-only | `7` | `7` | OK | — |
| `admin/musteriler.php` liste | foreach -> `getOrdersUntilNextGift/getGiftProgress` (in-memory math) | `2` | `2` | OK | — |
| `admin/mesajlar.php` liste | `getAllOrdered()` tek sorgu | `1` | `1` | OK | — |
| `admin/masalar.php` liste | `getMasalar()` tek sorgu + 60s cache | `1` | `1` | OK | — |
| `admin/temalar.php` liste | `getAll()` tek sorgu | `1` | `1` | OK | — |
| `admin/kategoriler.php` liste | `getAllWithProductCount()` JOIN'li tek sorgu | `1` | `1` | OK | — |
| `admin/activity-log.php` liste | Raw prepared query, foreach render | `1` | `1` | OK | — |

## Kritik Fix Detayi — `admin/garson.php`

### Onceki (N+1):
```php
$hazirSiparisler = $siparisService->getHazirSiparisler(); // 1 query

foreach ($hazirSiparisler as &$siparis) {
    // getSiparisDetay icinde: findOrFail + getSiparisKalemleri = 2 query
    $siparis['kalemler'] = $siparisService->getSiparisDetay((int)$siparis['id'])['kalemler'] ?? [];
}
```

Garson ekrani **30 saniyede bir** AJAX polling yapiyor. 10 hazir siparis ile:
- Eskiden: `1 + (10 x 2)` = **21 query / polling**
- Saatte 120 polling x 21 query = **2,520 query/saat/garson**
- 5 garson -> 12,600 query/saat sadece bu endpoint'te

### Sonrasi (1+1):
```php
$hazirSiparisler = $siparisService->getHazirSiparisler(); // 1 query

if (!empty($hazirSiparisler)) {
    $siparisRepo = new MasaSiparisRepository();
    $siparisIds  = array_map(static fn ($s) => (int)$s['id'], $hazirSiparisler);
    $kalemMap    = $siparisRepo->getKalemlerBySiparisIds($siparisIds); // 1 query (IN)
    foreach ($hazirSiparisler as &$siparis) {
        $siparis['kalemler'] = $kalemMap[(int)$siparis['id']] ?? [];
    }
    unset($siparis);
}
```

- Sabit **2 query** (N bagımsız)
- 5 garson x 120 polling x 2 = 1,200 query/saat (eskinin **%9.5**'i)
- `getKalemlerBySiparisIds()` zaten mevcut (Sprint 1 mutfak.php fix'inde kullanilan ayni metot)

Fix uygulanan bloklar:
- `admin/garson.php` satir 104-124 (AJAX GET endpoint)
- `admin/garson.php` satir 126-141 (sayfa ilk yukleme)

## Kapsam Disi Bulgular (Sprint 5+'e)

### 1. `admin/dashboard.php` — `getAllOrdered()` + array_slice(5)

```php
$allMessages = $mesajService->getAllOrdered();  // TUM mesajlar
$recentMessages = array_slice($allMessages, 0, 5);
```

**Sorun:** Mesaj tablosu buyudukce (ornegin 10,000 mesaj) bu 5 kayit icin de
tum tabloyu cekiyor. Row count'lari dusuk oldugu icin N+1 degil ama O(n)
memory ve transfer cost'u.

**Oneri (Sprint 5):** `MesajService::getRecent(int $limit): array` metodu ekle,
`LIMIT` SQL'i ile tek sorguda dondur. Su an "known-issue", hot path SLA disi.

### 2. `admin/raporlar.php` — 16 sira aggregate sorgu

16 tane `rapor_service()->...` + 9 tane `functions.php` aggregate cagrisi var.
Hepsi ayri query ama N+1 degil (foreach icinde DB yok).

**Oneri (Sprint 5):** Rapor ekranina **5 dakika cache** ekle (Cache::remember
ile key: `rapor:{baslangic}:{bitis}:{sekme}`). Rapor verisi real-time olmak
zorunda degil.

### 3. `admin/musteriler.php` — foreach icinde service call

```php
foreach ($musteriler as $musteri) {
    $sonrakiHediyeIcin = $musteriService->getOrdersUntilNextGift($musteri);
    $hedijeProgress = $musteriService->getGiftProgress($musteri);
}
```

**DB query YOK** — iki method da saf in-memory math (`$musteri['siparis_sayisi'] % 5`).
Yanlis pozitif. OK.

## Uygulanan Cozumun Etki Ozeti

| Metrik | Sprint 1 Oncesi | Sprint 1 Sonrasi | Sprint 4 Sonrasi |
|---|---:|---:|---:|
| Mutfak ekrani (30 siparis) | 31 query | 2 query | 2 query |
| Masa-siparisleri (20 siparis) | 1 (+ UI bug) | 2 query | 2 query |
| Garson polling (10 hazir) | **21 query** | 21 query | **2 query** |
| Dashboard | 3 query | 3 query | 3 query |
| Raporlar tam | ~25 query | ~25 query | ~25 query |

## Regresyon Riski

**Dusuk.** `getKalemlerBySiparisIds()` hem Sprint 1'de mutfak.php'de, hem
masa-siparisleri.php'de kullaniliyor; stabil API. Garson sayfasinin UI
kontrati da ayni `$siparis['kalemler']` dizisini bekliyor — kalem verisi
yapisi degismedi.

## Dogrulama

```bash
cd c:/xampp/htdocs/pastane
vendor/bin/phpunit           # 181 test yesil
vendor/bin/phpstan analyse   # 0 error
```

## Onerilen Sonraki Adimlar (Sprint 5)

1. **`MesajService::getRecent($limit)`** metodu ekle, dashboard'da kullan.
2. **Rapor cache** 5 dk TTL ile — sonraki big win.
3. **MySQL `general_log` sampling** cron — real production'da tespit edilmemis N+1
   yakalamak icin haftalik manuel inceleme.

---

**Status:** Sprint 4 kapsami kapandi. 1 kritik fix (garson.php AJAX + ilk yukleme).
Geri kalan bulgular Sprint 5'e devredildi.
