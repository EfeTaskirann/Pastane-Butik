# N+1 Query Audit — Sprint 1

**Tarih:** 2026-04-17
**Sorumlu:** Tech Lead
**Kapsam:** `src/Repositories/MasaRepository.php`, `src/Repositories/SiparisRepository.php`, `src/Repositories/MasaSiparisRepository.php`, `src/Repositories/MasaOturumRepository.php` ve bunları kullanan admin sayfaları.

## Özet

| Konum | Metot / Sayfa | Önce (N query) | Sonra (query sayısı) | Durum |
|---|---|---:|---:|---|
| `admin/mutfak.php` (sayfa ilk yükleme) | foreach → `getSiparisKalemleri($id)` | `1 + N` (N = aktif siparişler) | `2` (1 liste + 1 toplu IN) | FIX |
| `admin/mutfak.php` (AJAX GET) | foreach → `getSiparisKalemleri($id)` | `1 + N` | `2` | FIX |
| `admin/masa-siparisleri.php` | Kartlarda `$siparis['kalemler']` (servis yüklemiyordu) | UI boş kalıyordu / LAZY load potansiyeli | `2` (1 liste + 1 toplu IN) | FIX + BUGFIX |
| `MasaSiparisService::getBugununSiparisleri()` | İç N+1 yok — sadece liste | `1` | `1` | OK |
| `MasaSiparisRepository::getAktifSiparisler()` | Tek sorgu (LEFT JOIN ile masa_no) | `1` | `1` | OK |
| `MasaRepository::getMasalar()` (MasaService üzerinden) | Tek sorgu + 60s cache | `1` (ilk) / `0` (cache) | `1` / `0` | OK |
| `MasaOturumService::...` | `getAktifOturum()` tek sorgu | `1` | `1` | OK |

## Kritik Fix Detayları

### 1. `admin/mutfak.php` — aktif siparişler + kalemler

**Önce (N+1):**
```php
$tumSiparisler = $siparisService->getAktifSiparisler(); // 1 query
foreach ($tumSiparisler as &$ts) {
    $ts['kalemler'] = $siparisRepo->getSiparisKalemleri((int)$ts['id']); // N query
}
```

Örneğin 30 aktif sipariş → 31 query. Mutfak ekranı 30 saniyede bir yenilendiği için saatte 3720 query.

**Sonra (1+1):**
```php
$tumSiparisler = $siparisService->getAktifSiparisler(); // 1 query
$siparisIds    = array_map(static fn ($s) => (int)$s['id'], $tumSiparisler);
$kalemMap      = $siparisRepo->getKalemlerBySiparisIds($siparisIds); // 1 query (IN)
foreach ($tumSiparisler as &$ts) {
    $ts['kalemler'] = $kalemMap[(int)$ts['id']] ?? [];
}
```

Kullanılan repository metodu: `MasaSiparisRepository::getKalemlerBySiparisIds(array $siparisIds): array` — tek `WHERE siparis_id IN (...)` sorgusu, sonuç `siparis_id` bazında gruplanmış. Zaten mevcut ama çağrılmıyordu.

Düzeltilen bloklar:
- `admin/mutfak.php` satır 117-129 (AJAX GET endpoint)
- `admin/mutfak.php` satır 163-172 (sayfa ilk yükleme)

### 2. `admin/masa-siparisleri.php` — günün siparişleri + kalemler

**Önce:**
`MasaSiparisService::getBugununSiparisleri()` → yalnızca `masa_siparisleri` tablosundan liste döndürür; kalemler yüklenmez. Admin kartında `$kalemler = $siparis['kalemler'] ?? []` boş dizi olur → ürün listesi gözükmez (silent UI bug).

**Sonra:**
Admin sayfasında service çağrısının hemen ardından tek toplu kalem sorgusu:
```php
$siparisIdsN1 = array_map(static fn ($s) => (int)$s['id'], $siparisler);
$kalemMapN1   = $siparisRepoN1->getKalemlerBySiparisIds($siparisIdsN1);
foreach ($siparisler as &$sRef) {
    $sRef['kalemler'] = $kalemMapN1[(int)$sRef['id']] ?? [];
}
```

Bu hem bug fix hem N+1 önleme (gelecekte servise kalem loading eklenirse tek sorgu garantisi).

### 3. Yeni Repository Metodu — `MasaOturumRepository::closeInactiveSessions()`

N+1 ile doğrudan ilgili olmasa da cron job için eklendi. `masa_siparisleri` üzerinde `GROUP BY oturum_id` alt-sorgusu ile tek pass:

```sql
SELECT o.id, o.masa_id
FROM masa_oturumlari o
LEFT JOIN (
    SELECT oturum_id, MAX(siparis_zamani) AS son_siparis
    FROM masa_siparisleri GROUP BY oturum_id
) s ON s.oturum_id = o.id
WHERE o.durum = 'aktif'
  AND o.baslangic_zamani < (NOW() - INTERVAL ? MINUTE)
  AND (s.son_siparis IS NULL OR s.son_siparis < (NOW() - INTERVAL ? MINUTE))
```

Ardından transaction içinde 2 bulk UPDATE (oturumlar + masalar). Toplam 3 query, kaç oturum olursa olsun sabit.

## Etkilenen Dosyalar

- `admin/mutfak.php` — AJAX GET ve sayfa ilk yükleme blokları güncellendi.
- `admin/masa-siparisleri.php` — kalem yüklemesi eklendi.
- `src/Repositories/MasaOturumRepository.php` — `closeInactiveSessions()` eklendi.

## Regresyon Riski

- Düşük. `getKalemlerBySiparisIds()` zaten üretimde kullanılan bir API; güvenli semantik.
- `closeInactiveSessions()` yeni — transaction içinde, ayrıca `notlar` alanına `[Otomatik kapatildi - inaktivite timeout]` notu ekler.

## Test Önerisi

1. Mutfak ekranı 10+ aktif sipariş ile açılır, MySQL `general_log` gözlemlenir → query sayısı eskiden 11+, şimdi 3-4 (bootstrap dahil).
2. `bin/cron/masa-timeout.php 1` CLI'dan çalıştırılıp `storage/logs/cron-masa-timeout.log` incelenir.

## Kapsanan ve Kapsanmayanlar

- Kapsanan: admin mutfak, admin masa-siparisleri, cron job (yeni).
- Kapsanmayan: `menu/` dizini (müşteri tarafı QR menü) — Sprint 2'ye bırakıldı; `MasaOturumService` içinde sipariş/oturum/kalem yüklemeleri tek sorgu sayılabilir.
