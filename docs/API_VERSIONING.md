# API Versioning Stratejisi

**Durum:** Aktif
**Sürüm:** 1.0
**Son güncelleme:** 2026-04-17
**Sahip:** Tech Lead

## 1. Amaç

Tatlı Düşler API'sinin zaman içinde istemci uygulamaları (web, iOS/Android, QR menü,
3. taraf entegrasyonlar) bozmadan evrilebilmesi için net bir sürüm yönetimi
politikası tanımlamak.

## 2. Versiyonlama Yöntemi: **URL Tabanlı** (`/api/v{major}`)

**Seçim:** Tüm v1 endpoint'leri `/api/v1/...` altında yer alır.

### Neden URL tabanlı?

| Kriter | URL (`/api/v1/`) | Accept header (`application/vnd.pastane.v1+json`) | Query (`?v=1`) |
|---|---|---|---|
| Keşfedilebilirlik | ✅ Tarayıcı, curl, Postman'de görünür | ❌ Gizli | ⚠️ Karışıyor |
| Cache-friendly | ✅ Her sürüm ayrı URL | ⚠️ Vary header gerekir | ⚠️ Query string cache |
| Proxy/CDN kuralları | ✅ Path bazlı yönlendirme kolay | ❌ Header bazlı karışık | ❌ Zor |
| Tarayıcı dost | ✅ Doğrudan linklenebilir | ❌ | ⚠️ |
| Router karmaşıklığı | ✅ Basit (`Router::prefix('/v1')`) | ❌ Middleware zorunlu | ❌ |
| Gerçek kullanım | Stripe, GitHub, Twitter, Shopify | GitHub (paralel), GraphQL | Az |

URL tabanlı yaklaşım mevcut yönlendirme altyapısıyla (`api/v1/index.php` +
`routes/*.php`) birebir uyumludur ve sıfır ek middleware gerektirir.

## 3. Sürüm Numaralandırma (SemVer'e Yakın)

API sürümleri **yalnızca major** düzeyde URL'de yer alır (`/api/v1`, `/api/v2`).
Minor ve patch değişiklikleri aynı major içerisinde **geriye uyumlu** kabul edilir.

| Tür | Örnek | URL değişir mi? | Deprecation? |
|---|---|---|---|
| **Major** (breaking) | `GET /urunler` yanıt şeması değişti | Evet → `/api/v2/urunler` | 6 ay v1 de yayında kalır |
| **Minor** (additive) | Yeni opsiyonel query param, yeni endpoint | Hayır | Yok |
| **Patch** (bugfix) | 500 yerine 422 dönme düzeltmesi | Hayır | Yok |

## 4. Breaking Change Politikası

Aşağıdakiler **breaking** kabul edilir ve yeni major sürüm (v2) gerektirir:

1. Var olan bir endpoint'in kaldırılması veya yolunun değişmesi
2. Response alanının kaldırılması veya tipinin değişmesi
3. Request alanının **zorunlu hale** gelmesi (yeni zorunlu alan)
4. Enum değerinin kaldırılması (`durum` enum'dan `onaylandi` çıkarılırsa)
5. HTTP durum kodunun anlamsal olarak değişmesi (200 → 202 gibi)
6. Kimlik doğrulama yönteminin değişmesi (JWT → OAuth2)
7. Rate-limit semantiğinin daralması (aynı istemciyi kıracak şekilde)

Aşağıdakiler **breaking değildir**:

- Yeni opsiyonel query/body alanı eklemek
- Yeni response alanı eklemek (istemci bilinmeyen alanları yoksaymalıdır)
- Yeni endpoint, yeni enum değeri eklemek (tüketiciler güvenli default ile)
- Hata mesajı metninin değişmesi (hata kodu/HTTP kodu aynı kaldığı sürece)
- Performans iyileştirmeleri

## 5. Deprecation Süreci

Bir endpoint veya alan sonlandırılacaksa şu süreç uygulanır:

```
t0        Duyuru + docs'a "DEPRECATED" etiketi
t0 + 1ay  Response'a Sunset + Deprecation + Link header'ları eklenir
t0 + 6ay  Kaldırılır (404 / 410 Gone)
```

### 5.1 HTTP Başlıkları (RFC 8594, IETF draft-deprecation)

Deprecate edilmiş endpoint her yanıtta şu header'ları döner:

```
Deprecation: true
Sunset: Sat, 31 Oct 2026 23:59:59 GMT
Link: <https://tatlidusler.local/docs/api/migrations/v1-v2.md>; rel="deprecation"; type="text/markdown"
Link: <https://tatlidusler.local/api/v2/urunler>; rel="successor-version"
Warning: 299 - "This endpoint is deprecated. Use /api/v2/urunler. Sunset: 2026-10-31."
```

Uygulamada yardımcı fonksiyon:

```php
// includes/helpers.php (öneri — henüz yazılmadı)
function api_deprecate(string $sunsetDate, string $successorUrl, string $migrationDocUrl): void {
    header('Deprecation: true');
    header('Sunset: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime($sunsetDate)));
    header('Link: <' . $migrationDocUrl . '>; rel="deprecation"; type="text/markdown"', false);
    header('Link: <' . $successorUrl . '>; rel="successor-version"', false);
    header('Warning: 299 - "Bu endpoint deprecated. Yerine: ' . $successorUrl . '"');
}
```

### 5.2 Minimum Süreler

| Değişiklik | Minimum uyarı süresi |
|---|---|
| Major kaldırma | **6 ay** |
| Field kaldırma (breaking) | 3 ay + yeni major'da |
| Rate limit düşürme | 30 gün |
| Hata kodu değişimi | 30 gün |

## 6. Sürüm Yayın Süreci (Release Protocol)

Yeni bir major (v2) çıkarken:

1. `api/v2/` dizini açılır, `api/v1/` korunur.
2. `docs/api/openapi-v2.yaml` oluşturulur.
3. `docs/api/migrations/v1-to-v2.md` yazılır (adım adım değişiklik rehberi).
4. Staging'de 2 hafta paralel çalıştırılır.
5. Duyuru: e-posta + `CHANGELOG.md` + API docs banner.
6. v1 endpoint'leri `Deprecation: true` header eklemeye başlar.
7. 6 ay sonra v1 kapatılır (`410 Gone` + migration link'i döner).

## 7. Migration Guide Yazma Protokolü

Her major sürüm için `docs/api/migrations/v{N}-to-v{N+1}.md` şablonu:

```markdown
# v1 → v2 Migration Guide

## Özet
Hangi istemciler etkileniyor, neden değişti.

## Breaking Changes
### [BREAKING] POST /siparisler — `kisi_sayisi` yerine `adet`
**Eski (v1):** `{ "kisi_sayisi": 2 }`
**Yeni (v2):** `{ "adet": 2 }`
**Geçiş:** Alan adını değiştir, semantik aynı.
**Sunset:** 2026-10-31

### [BREAKING] GET /urunler — `fiyat` artık string yerine number
...

## Yeni Özellikler
- POST /urunler/batch — toplu ürün ekleme

## İstemci Kodu Örnekleri
```diff
- fetch('/api/v1/siparisler', { body: JSON.stringify({ kisi_sayisi: 2 }) })
+ fetch('/api/v2/siparisler', { body: JSON.stringify({ adet: 2 }) })
```

## Test Checklist
- [ ] Tüm request body'leri yeni alan adlarıyla güncellendi
- [ ] Response tipleri (string → number) parser'da güncellendi
- [ ] Hata kodu değişimleri handle edildi
```

## 8. Contract Testing (Backward Compatibility)

Her v{N} için **contract test suite** tutulur:

- `tests/Contract/V1/` — v1 sözleşmesini kilitler
- PHPUnit ile `response schema` assert edilir (openapi.yaml şemasına karşı)
- `league/openapi-psr7-validator` veya manuel JSON Schema matcher kullanılır

Örnek (öneri):

```php
// tests/Contract/V1/SiparisContractTest.php
public function testGetSiparisResponseMatchesV1Schema(): void
{
    $response = $this->client->get('/api/v1/siparisler/1');
    $this->assertMatchesOpenApiSchema(
        $response->getBody(),
        'docs/api/openapi.yaml',
        '/siparisler/{id}',
        'get',
        '200'
    );
}
```

**CI gate:** PR'da v1 contract test kırılırsa merge engellenir.
Breaking change zorunluluğu varsa:
1. v2 oluştur
2. v1 contract testini `@group legacy` ile işaretle
3. v1 endpoint'ine deprecation header ekle

## 9. Versiyonlama Anti-Pattern'leri (YAPMA)

- ❌ Aynı URL'de sessizce breaking yapmak
- ❌ Response şemasını değiştirip "feature flag" ile açmak
- ❌ Query parametresinin varsayılanını değiştirmek (örn. `?limit=20 → 50`)
- ❌ HTTP kodunu 200 → 201'e "düzeltme" adıyla değiştirmek
- ❌ 3'ten fazla aktif major'ı aynı anda desteklemek

## 10. İstemci Yükümlülükleri (Client Etiquette)

API tüketicileri şu kurallara uymalıdır:

1. **Bilinmeyen alanları yoksay** — yeni alan eklense bozulmasın
2. **Versiyon kilitli istek** — URL'de `/v1/` açık yazılsın
3. **Sunset header'ı izle** — log'la ve uyarı göster
4. **Zaman damgası UTC** — tüm tarihler ISO-8601 UTC
5. **Retry-After'a uy** — 429'da bekle

## 11. Örnek Zaman Çizelgesi (Varsayımsal v2 Geçişi)

| Tarih | Olay |
|---|---|
| 2026-05-01 | v2 duyurusu + migration guide yayını |
| 2026-05-15 | v2 staging'de, paralel v1 yayında |
| 2026-06-01 | v2 production'a çıkar, v1 `Deprecation: true` döner |
| 2026-09-01 | v1 son uyarı bildirimi (3 ay kaldı) |
| 2026-12-01 | v1 → `410 Gone`, sadece v2 |

## 12. Kaynaklar

- RFC 8594 — Sunset HTTP Header
- IETF draft-ietf-httpapi-deprecation-header
- Stripe API Versioning: https://stripe.com/blog/api-versioning
- OpenAPI 3.0.3 Specification
- `docs/api/openapi.yaml` (projenin live spec'i)
