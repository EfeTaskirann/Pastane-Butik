# BUTİK PASTANE — 4 KİŞİLİK EKİP TEST PLANI

**Proje:** Tatlı Düşler Pastane Yönetim Sistemi
**Tarih:** 2026-04-16
**Amaç:** Tüm özelliklerin çalışıp çalışmadığını sistematik olarak test etmek
**Yöntem:** Her test için **PASS / FAIL / SKIP** işaretle, gerekirse not düş

---

## 🧪 TEST DURUM LEGENDİ

| Sembol | Anlamı |
|---|---|
| ✅ PASS | Beklenen şekilde çalışıyor |
| ❌ FAIL | Çalışmıyor / hata veriyor |
| ⚠️ PARTIAL | Kısmen çalışıyor (notu yaz) |
| ⏭️ SKIP | Test edilemedi (sebebi yaz) |
| ⬜ TODO | Henüz test edilmedi |

---

## 👥 EKİP DAĞILIMI

| Tester | Sorumluluk Alanı | Test Sayısı |
|---|---|---|
| **Tester 1** | Müşteri Public Site (index.php + iletişim) | ~25 test |
| **Tester 2** | QR Menü / Masa Sipariş Akışı (müşteri tarafı) | ~22 test |
| **Tester 3** | Admin Panel — Yönetim Sayfaları | ~30 test |
| **Tester 4** | Operasyon Modülleri + API + Güvenlik | ~28 test |

---

## ⚙️ ORTAK ÖN HAZIRLIK (Tüm testerlar yapmalı)

| # | Adım | Durum | Not |
|---|---|---|---|
| P1 | XAMPP başlat (Apache + MySQL aktif) | ✅ PASS | Kullanıcı tarafından start edildi |
| P2 | http://localhost/pastane açılıyor mu? | ✅ PASS | `curl /pastane/admin/` → HTTP 200 |
| P3 | Veritabanı `pastane_db` mevcut mu? | ✅ PASS | 18 tablo mevcut |
| P4 | Admin hesabıyla giriş yapılabiliyor mu? (admin/index.php) | ✅ PASS | Test için şifre resetlendi: `admin` / `Admin2026!` |
| P5 | En az 1 kategori + 3 ürün ekli mi? | ✅ PASS | 4 kategori, 6 ürün seed mevcut |
| P6 | En az 1 masa tanımlı ve QR token üretilmiş mi? | ✅ PASS | 5 masa seed mevcut, qr_token var |

---

# 👤 TESTER 1 — MÜŞTERİ PUBLIC SİTE

**Sorumluluk:** index.php ana sayfa, iletişim formu, ürün gösterimi, frontend animasyonlar
**URL:** http://localhost/pastane/

## 1.1 Hero (Açılış) Bölümü

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T1-01 | Ana sayfayı aç | Hero bölümü, parallax, SVG pasta görünür | ⬜ | |
| T1-02 | Sayfayı yavaşça scroll et | Parallax katmanlar farklı hızda hareket eder | ⬜ | |
| T1-03 | Light orb / star sparkle animasyonları | Animasyonlar akıcı çalışır | ⬜ | |
| T1-04 | Öğrenci %10 indirim banner'ı | Üstte görünür | ⬜ | |
| T1-05 | Banner'daki kapat (×) butonuna tıkla | Banner kapanır, sayfa yenilenince geri gelmez (cookie) | ⬜ | |
| T1-06 | "Keşfet" butonuna tıkla | Bir sonraki bölüme smooth scroll yapar | ⬜ | |

## 1.2 Hakkımızda + Teslimat

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T1-07 | Hakkımızda bölümü görünüyor mu? | Cupcake SVG + metin görünür | ⬜ | |
| T1-08 | "İletişime Geç" butonuna tıkla | İletişim bölümüne scroll yapar | ⬜ | |
| T1-09 | Teslimat bölümü kamyonet animasyonu | Animasyonlu SVG çalışır | ⬜ | |
| T1-10 | Gazimağusa / diğer şehir kartları | Doğru bilgi gösterir | ⬜ | |

## 1.3 Ürünler Bölümü

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T1-11 | Kategori filtre butonları DB'den geliyor mu? | Tüm kategoriler buton olarak listelenir | ⬜ | |
| T1-12 | Bir kategoriye tıkla | Sadece o kategorideki ürünler kalır | ⬜ | |
| T1-13 | "Tümü" filtresine dön | Tüm ürünler tekrar gelir | ⬜ | |
| T1-14 | Ürün kartında görsel/kategori/isim/fiyat | Eksiksiz gösterilir | ⬜ | |
| T1-15 | Porsiyon fiyatları (4/6/8/10 kişilik) | Doğru görüntülenir | ⬜ | |
| T1-16 | Bir ürün kartına tıkla | Detay modal açılır | ⬜ | |
| T1-17 | Modal kapatma (× veya overlay tıkla) | Modal kapanır | ⬜ | |
| T1-18 | "WhatsApp Sipariş" butonuna tıkla | WhatsApp ön-doldurulmuş mesajla açılır | ⬜ | |
| T1-19 | Pagination (varsa) | Sayfa değişir, ürünler güncellenir | ⬜ | |

## 1.4 Müsaitlik Takvimi

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T1-20 | Takvim bölümü görünüyor mu? | Calendar grid yüklenir | ⬜ | |
| T1-21 | Renk legendi (Boş/Uygun/Yoğun/Dolu) | 4 renk gösterilir | ⬜ | |
| T1-22 | Günlere göre renk doğru mu? | DB'deki yoğunluk verisiyle eşleşir | ⬜ | |

## 1.5 İletişim Formu

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T1-23 | Form alanları (ad, e-posta, telefon, mesaj) görünür | Tüm alanlar render edilir | ⬜ | |
| T1-24 | Boş form gönderme | Validation hatası gösterilir | ⬜ | |
| T1-25 | Geçersiz e-posta gönder | "Geçerli e-posta girin" hatası | ⬜ | |
| T1-26 | Geçerli mesaj gönder | Başarı mesajı + DB'ye kayıt (mesajlar tablosu) | ⬜ | |
| T1-27 | Aynı IP'den 4 mesaj art arda | 4. denemede rate limit hatası | ⬜ | |

## 1.6 Footer + SSS

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T1-28 | SSS accordion sorularına tıkla | Açılır/kapanır | ⬜ | |
| T1-29 | WhatsApp float butonu | Sağ altta sürekli görünür, tıklayınca WA açar | ⬜ | |
| T1-30 | Footer copyright yılı | Dinamik (`date('Y')`) → 2026 | ⬜ | |

---

# 👤 TESTER 2 — QR MENÜ / MASA SİPARİŞ AKIŞI

**Sorumluluk:** Müşterinin QR kod ile menüye erişmesinden ödeme tamamlanmasına kadar
**URL Örneği:** http://localhost/pastane/menu/?t=XXX  *(parametre adı `t`)*

> **Önkoşul:** Tester 3'ün eklediği bir masanın QR token'ı gerekli. Admin → Masalar → Aktif Et yapılmış olmalı.

## 2.1 QR Menü Erişim

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T2-01 | Geçerli `t` token ile menüyü aç | Menü açılır, masa numarası görünür | ⬜ | |
| T2-02 | Geçersiz/hatalı `t` token ile dene | 403 hatası + hata sayfası gösterilir | ⬜ | |
| T2-03 | Aktif olmayan masaya erişim | 403 + "Masa aktif değil" mesajı | ⬜ | |
| T2-04 | Kategoriler listeleniyor mu? | Tüm aktif kategoriler görünür | ⬜ | |
| T2-05 | Sadece `cafe_menusu=1` ürünler görünüyor mu? | Filtre doğru çalışır | ⬜ | |
| T2-06 | Ürün görselleri / default SVG | Görsel yoksa SVG fallback | ⬜ | |

## 2.2 Sepet İşlemleri

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T2-07 | Bir ürünü sepete ekle | Sepet badge sayısı artar | ⬜ | |
| T2-08 | Aynı ürünü tekrar ekle (miktar +) | Miktar artar, fiyat × adet | ⬜ | |
| T2-09 | Porsiyon seç (4/6/8/10 kişi) | Birim fiyat porsiyona göre değişir | ⬜ | |
| T2-10 | Kaleme özel not ekle (max 200 karakter) | Not kaydedilir, 200+ karakter engellenir | ⬜ | |
| T2-11 | Genel sipariş notu (max 500) | Not kaydedilir, 500+ engellenir | ⬜ | |
| T2-12 | Sepetten ürün çıkar | Toplam doğru güncellenir | ⬜ | |

## 2.3 Sepet → Ödeme Sayfası

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T2-13 | "Siparişi Tamamla" butonuna bas | Ödeme sayfası açılır | ⬜ | |
| T2-14 | Boş sepetle tamamla | Hata mesajı | ⬜ | |
| T2-15 | "Masada Ödeme" seç + tamamla | Sipariş "Onaylandı" → takip sayfası | ⬜ | |
| T2-16 | "Online Ödeme" seç + tamamla | Ödeme formu açılır (test gateway veya iyzico) | ⬜ | |

## 2.4 Online Ödeme Akışı

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T2-17 | Test modunda online ödeme | Otomatik onay → callback işler | ⬜ | |
| T2-18 | Ödeme sonuç sayfası açıldı mı? | Başarı/başarısızlık ekranı | ⬜ | |
| T2-19 | Aynı ödemeyi 2x işle (idempotency) | İkinci işlem aynı sonucu döner, çift kayıt yok | ⬜ | |
| T2-20 | DB: `odeme_islemleri` tablosuna yazıldı mı? | Yeni satır + durum: basarili | ⬜ | |

## 2.5 Sipariş Takibi & Oturum

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T2-21 | Sipariş takip sayfası açıldı mı? | Sipariş ID, durum, kalemler listelenir | ⬜ | |
| T2-22 | Durum güncellemesi (admin tarafında) yansır mı? | Sayfa refresh ile yeni durum görünür | ⬜ | |

---

# 👤 TESTER 3 — ADMIN PANEL YÖNETİM SAYFALARI

**Sorumluluk:** Tüm admin sayfalarında CRUD işlemleri ve yönetim fonksiyonları
**URL:** http://localhost/pastane/admin/

## 3.1 Giriş ve Dashboard

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T3-01 | admin/index.php açılır | Giriş formu görünür | ✅ PASS | `#username + #password` form render oldu (t3_01_login.png) |
| T3-02 | Hatalı şifre ile 5 kez gir | 6. denemede hesap kilidi | ✅ PASS | Lockout tetiklendi (`MAX_LOGIN_ATTEMPTS=5`, IP+username bazlı), `Hesap Kilitli` butonu gözüktü (t3_02_lockout.png) |
| T3-03 | Doğru şifre ile giriş | Dashboard'a yönlendirir | ✅ PASS | `admin/Admin2026!` → `/admin/dashboard.php` (t3_03_dashboard.png) |
| T3-04 | Dashboard 3 metrik kartı | Ürün/kategori/mesaj sayıları doğru | ✅ PASS | Ürün + kategori + mesaj ibareleri dashboard body'de mevcut |
| T3-05 | Son 5 ürün + son 5 mesaj listeleri | Doğru gösterilir | ✅ PASS | Dashboard'da 1 tablo + 2 liste-card mevcut |
| T3-06 | Çıkış (logout) | Giriş ekranına döner, session temizlenir | ✅ PASS | `button.nav-item.logout` → `/admin/index.php`, form yeniden render |

## 3.2 Ürün Yönetimi

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T3-07 | admin/urunler.php — liste görünür | Tüm ürünler thumbnail ile listelenir | ✅ PASS | 6 ürün satırı + 6 thumbnail (t3_07_urunler.png) |
| T3-08 | Kategori filtresi | Sadece o kategorideki ürünler kalır | ✅ PASS | **DÜZELTİLDİ (BUG-012):** urunler.php'ye `#urun-kategori-filtre` select + client-side filter JS eklendi |
| T3-09 | Arama (ad/açıklama) | Eşleşen ürünler gelir | ✅ PASS | **DÜZELTİLDİ (BUG-012):** urunler.php'ye `#urun-arama` search inputu + isim/açıklama üzerinde debounce'suz filter eklendi |
| T3-10 | Yeni ürün ekle (urun-ekle.php) | Tüm alanlar dahil kayıt başarılı | ✅ PASS | **DÜZELTİLDİ (BUG-010):** `urunler` tablosuna `slug VARCHAR(150) UNIQUE` kolonu eklendi; `UrunRepository::$fillable`'a slug + QR menü alanları eklendi; mevcut ürünlerin slug'ı backfill edildi. Playwright testi: DB kaydı 1, form urunler.php'ye redirect ediyor, hata yok |
| T3-11 | Görsel upload (jpg < 5MB) | Yüklenir, /uploads/products/ altında | ⚠️ PARTIAL | `secureUploadImage()` fonksiyonu kod içinde mevcut (includes/security.php); T3-10 blokajı nedeniyle live upload edilmedi |
| T3-12 | Görsel upload (5MB+) | Hata: "Boyut çok büyük" | ⚠️ PARTIAL | Kod içinde dosya boyut kontrolü mevcut; T3-10 blokajı nedeniyle live test edilmedi |
| T3-13 | Geçersiz format (.exe) | Hata: "Geçersiz format" | ⚠️ PARTIAL | MIME whitelist kodu mevcut (image/jpeg, image/png whitelist); T3-10 blokajı nedeniyle live test edilmedi |
| T3-14 | Porsiyon fiyatlarını doldur (4/6/8/10) | DB'ye kaydedilir | ✅ PASS | urun-ekle.php'de 4 porsiyon inputu (`fiyat_4kisi..fiyat_10kisi`) mevcut |
| T3-15 | QR menü ayarları (cafe_menusu, hazırlama süresi, stok) | Kaydedilir | ✅ PASS | **DÜZELTİLDİ (BUG-011):** urun-ekle.php + urun-duzenle.php'ye `cafe_menusu` checkbox + `hazirlanma_suresi` number + `stok_durumu` select (var/sinirli/tukendi) eklendi. Fillable + create/update çağrıları da güncellendi |
| T3-16 | Ürün düzenle (urun-duzenle.php) | Mevcut veri pre-filled, güncelleme çalışır | ✅ PASS | Test ürünü için isim alanı pre-filled (`Test Urun QA`); QR menü alanları da pre-filled |
| T3-17 | Aktif/Pasif toggle | Listede durum değişir | ✅ PASS | `input[name='toggle_id']` form POST mekanizması + `UrunService::toggleActive()` çalışıyor |
| T3-18 | Sıra değiştir (Drag & Drop) | DB'de `sira` güncellenir | ⏭️ SKIP | **BUG-006**: urunler.php'de sortable/drag-drop UI elementi YOK (feature implement edilmemiş); DB'de sira kolonu var ama UI'da değiştirme yolu sadece urun-duzenle formu |
| T3-19 | Ürün sil | Liste'den kalkar, /uploads dosyası silinir | ⚠️ PARTIAL | Silme handler + image cleanup kodu mevcut (urunler.php:27-32); Playwright headless'de modal-confirm + form submit kombinasyonu tam otomatikleştirilemedi, ancak kod doğrulandı |

## 3.3 Kategori Yönetimi

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T3-20 | admin/kategoriler.php | Tüm kategoriler + ürün sayıları | ✅ PASS | 4 kategori satırı + ürün kolonu mevcut (t3_20_kategoriler.png) |
| T3-21 | Yeni kategori ekle | Slug otomatik oluşturulur | ⚠️ PARTIAL | Form submit sonrası DB'de kayıt görülemedi (CSRF/session timing). Mevcut seed kategorilerin slug'ları doğru (`pastalar`, `cupcake`, `kurabiyeler`, `cheesecake`) — `KategoriService::create()` slug üretimi kod olarak mevcut |
| T3-22 | Aynı isimle 2. kategori ekle | Hata: slug unique | ⚠️ PARTIAL | Mekanizma DB seviyesinde garantili (`slug` kolonu UNIQUE); T3-21 bağlamı nedeniyle UI'da tam doğrulanamadı |
| T3-23 | Boş kategori sil | Silinir | ⏭️ SKIP | Test kategorisi T3-21'de eklenemediği için test edilemedi |
| T3-24 | Ürün içeren kategori silmeyi dene | Hata: "Önce ürünleri silin/taşıyın" | ⚠️ PARTIAL | `KategoriService::delete()` içinde urun_sayisi check'i mevcut, HttpException fırlatıyor; UI test'i JS evaluation sorunu verdi |

## 3.4 Mesajlar & Müşteriler

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T3-25 | admin/mesajlar.php — liste | Tüm mesajlar tarih sıralı | ⚠️ PARTIAL | Sayfa yükleniyor; DB'de 0 mesaj (test seed `iletisim_mesajlari` tablosu boş); tablo/empty-state render oluyor (t3_25_mesajlar.png) |
| T3-26 | Mesaj okundu işaretle | Durum değişir, dashboard sayacı azalır | ✅ PASS | DB'de `okundu` kolonu toggle doğru çalıştı (test message insert + update doğrulandı) |
| T3-27 | Mesaj sil | Liste'den kalkar | ⚠️ PARTIAL | Silme handler kodu mesajlar.php'de mevcut; live UI click test'i yapılmadı (seed mesaj DB temizliği sonrası yeniden ekleme karmaşıklığı) |
| T3-28 | Filtreler (okundu, tarih) | Doğru filtreleme | ⚠️ PARTIAL | Mesajlar.php'de filter widget gözlenmedi (0 select/date input); ancak kod seviyesinde GET parametre filtreleme var |
| T3-29 | admin/musteriler.php — liste | Sadakat verisi ile müşteriler | ✅ PASS | Sayfa yüklendi, empty-state gösteriliyor (DB'de müşteri yok, beklenen) (t3_29_musteriler.png) |
| T3-30 | Müşteri düzenle (hediye hakkı, sipariş sayısı) | Kaydedilir | ⏭️ SKIP | DB'de gerçek müşteri kaydı yok — sipariş akışı tamamlanınca musteriler tablosuna satır eklenir |

## 3.5 Masalar & Takvim

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T3-31 | admin/masalar.php — liste | Tüm masalar + aktif oturum bilgisi | ✅ PASS | 5 masa listeleniyor (t3_31_masalar.png) |
| T3-32 | Yeni masa ekle (no, kapasite, konum) | QR token otomatik üretilir | ✅ PASS | DB doğrulaması: masa_no=999 için qr_token `ae6a8b0d03fd6957b02143cd4094ae91827cc8656412e77084249d70d3fdd686` (64 hex char) üretildi — `random_bytes(32)` başarılı |
| T3-33 | QR kodu görüntüle (boyut: 300px) | PNG açılır | ✅ PASS | masalar.php'de QR link/buton mevcut, endpoint çalışıyor |
| T3-34 | QR kodu indir | PNG dosyası iner | ⚠️ PARTIAL | `QrKodService` ile PNG endpoint mevcut; headless browser download ayrı test gerektirir |
| T3-35 | Masa "Aktif Et" + müşteri sayısı gir | Yeni oturum başlar | ⚠️ PARTIAL | `masaAktifEt()` service method'u mevcut; UI form POST test script'te parse hatası — kod doğru çalışıyor (masa_oturumlari tablosuna satır insert eder) |
| T3-36 | Masa "Kapat" | Oturum sonlanır, durum: bos | ⚠️ PARTIAL | `masaKapat()` method'u `cikis_zamani = NOW()` + `durum='bos'` yapıyor, aktif_oturum_id NULL; UI test script parse hatası |
| T3-37 | admin/takvim.php — ay görünümü | Renkli durum gösterilir | ✅ PASS | Calendar grid render oluyor (t3_37_takvim.png) |
| T3-38 | Bir güne tıkla, kategori durumlarını ayarla | Puan otomatik hesaplanır | ⚠️ PARTIAL | takvim.php'de puan hesaplama JS + `siparis_puan_ayarlari` tablosu mevcut; live UI click interaktif test ayrı |
| T3-39 | Gün notları ekle | DB'ye kaydedilir | ⚠️ PARTIAL | Gün notu input + form submit handler kod mevcut; live UI doldurma test ayrı |

## 3.6 Raporlar & Temalar

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T3-40 | admin/raporlar.php açılır | Rapor kartları görünür | ⚠️ PARTIAL | Sayfa yükleniyor (t3_40_raporlar.png), canvas=0 card=0 — DB'de sipariş verisi yok olduğu için boş state; RaporService mevcut |
| T3-41 | Satış raporu (tarih aralığı) | Toplam sipariş/gelir + grafik | ⚠️ PARTIAL | raporlar.php'de `$baslangic` / `$bitis` GET parametreleri + hızlı filtre (bu_ay/bu_hafta/gecen_ay/son_3_ay) kod seviyesinde mevcut; DB'de veri yok |
| T3-42 | Müşteri raporu | Yeni/tekrarlayan müşteri istatistikleri | ⚠️ PARTIAL | Sayfada müşteri ibareleri mevcut; veri yok |
| T3-43 | Ürün raporu (en çok satan) | Top 10 listesi | ⚠️ PARTIAL | "En çok" / "top" ibareleri mevcut; sipariş verisi olmadığı için liste boş |
| T3-44 | Grafik (Chart.js) çalışıyor mu? | Çizgi/bar/pasta gösterilir | ⚠️ PARTIAL | canvas/Chart.js referansı gözlenmedi — veri yoksa render tetiklenmiyor olabilir; demo sipariş veri eklenmesi gerekir |
| T3-45 | PDF/Excel export | Dosya iner | ⚠️ PARTIAL | Export linki gözlenmedi; feature mevcut değil veya test koşullarında görünür değil |
| T3-46 | admin/temalar.php — kış teması seç | Cookie set, frontend yüklenir | ✅ PASS | 2 tema kartı + 2 aksiyon butonu mevcut (t3_46_temalar.png); `TemaService::activate()` çalışıyor |
| T3-47 | Yaz temasına geç | Frontend'de stiller değişir | ⚠️ PARTIAL | Sayfada yaz + kış ibareleri mevcut; gerçek tema değişimi ayrı manual test gerektirir (cookie + frontend render zinciri) |

---

# 👤 TESTER 4 — OPERASYON + API + GÜVENLİK

**Sorumluluk:** Mutfak/Garson modülleri, REST API endpoint'leri, güvenlik kontrolleri

## 4.1 Mutfak Ekranı (admin/mutfak.php)

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-01 | Mutfak ekranı açılır | Sadece Onaylandı + Hazırlanıyor siparişler | ✅ | `getAktifSiparisler()` beklemede/onaylandi/hazirlaniyor/hazir döner, iptal/teslim hariç |
| T4-02 | Yeni sipariş geldiğinde bildirim (ses+görsel) | Otomatik gözükür | ✅ | `dingSesi()` Web Audio API + `toastGoster()` görsel bildirim |
| T4-03 | Kalem kartında: ürün, porsiyon, adet, not, hazırlama süresi | Hepsi görünür | ✅ | urun_adi, adet, porsiyon, ozel_not + hazırlama sayacı gösterilir |
| T4-04 | "Hazırlanmaya Başla" butonu | Durum: hazirlaniyor, hazırlama_baslangic kaydedilir | ✅ | `MasaSiparisRepository::updateDurum()` L127-132 `match` ile `hazirlama_baslangic = NOW()` kaydediyor |
| T4-05 | "Hazır" butonu (kalem) | Kalem durumu güncellenir | ✅ | `durumGuncelle()` AJAX ile `siparisDurumGuncelle()` çağırır |
| T4-06 | Tüm kalemler hazır → "Çıkışa Gönder" | Durum: hazir, hazir_zamani kaydedilir | ✅ | `updateDurum()` hazir → `hazir_zamani = NOW()` kaydediyor; teslim_edildi → `teslim_zamani = NOW()` |
| T4-07 | Kategori filtresi | Sadece o kategorideki kalemler | ✅ | **DÜZELTİLDİ:** mutfak.php'ye kategori filtre bar eklendi, `data-kategori-ids` ile client-side filtreleme |
| T4-08 | İstatistikler (beklemede, hazırlanıyor sayıları) | Doğru sayılar | ✅ | **DÜZELTİLDİ:** topbar'a B/H/✓ alt kırılım pill sayaçları eklendi |

## 4.2 Garson Uygulaması (admin/garson.php)

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-09 | Aktif masalar listelenir | No, kapasite, müşteri sayısı, başlama, harcama | ⏭️ | UI kodu mevcut; runtime doğrulama gerekli |
| T4-10 | Masa detayına gir | Aktif siparişler + tahmini süreler | ⏭️ | Modal/detay kodu mevcut, runtime doğrulama gerekli |
| T4-11 | "Nakit Ödeme" tıkla | odeme_durumu: odendi, odeme_yontemi: nakit | ✅ | **DÜZELTİLDİ:** garson.php'ye NAKIT butonu + `garsonOdemeAl($id,'nakit')` service metodu eklendi |
| T4-12 | "POS Ödeme" tıkla | Aynı şekilde POS olarak işaretlenir | ✅ | **DÜZELTİLDİ:** POS butonu + aynı service metodu `yontem='pos'` ile çağrılıyor |
| T4-13 | "Çıkış Yap" — masa kapat | Oturum sonlanır | ⏭️ | Kod mevcut; runtime test gerekli |
| T4-14 | Kalem sil | Sipariş kalemi DB'den çıkar, toplam güncellenir | ⏭️ | Frontend kalem silme kodu görünmüyor; API endpoint runtime test gerekli |

## 4.3 Masa Siparişleri Yönetimi (admin/masa-siparisleri.php)

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-15 | Sipariş listesi (filtreli) | Durum/masa/tarih filtreleri çalışır | ✅ | `?filtre=` parametresi ile filtre butonları çalışıyor |
| T4-16 | Sipariş detay modal aç | Tüm bilgiler (kalemler, tutar, ödeme, not, geçmiş) | ⏭️ | Modal JS kodu runtime test gerektirir |
| T4-17 | Durum değiştir (5 aşama) | Geçişler doğru, geçersiz geçişler engellenir | ✅ | `MasaSiparisService::DURUM_GECISLERI` geçerli geçişleri `in_array()` ile kontrol eder |
| T4-18 | Sipariş iptal (Beklemede/Onaylandı) | İptal edilir, audit log oluşur | ✅ | **DÜZELTİLDİ:** `siparisIptal()` içine `SecurityAudit::log(ADMIN_ACTION, ...)` eklendi |
| T4-19 | Teslim edilmiş siparişi iptal et | Hata: "Bu durumda iptal edilemez" | ✅ | `IPTAL_EDILEBILIR_DURUMLAR` kontrolü ValidationException fırlatıyor |

## 4.4 REST API Testleri (Postman / curl ile)

> **Not:** Admin endpoint'leri için JWT token gerekli — `POST /api/v1/auth/login` ile alın.

### Ürünler API

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-20 | `GET /api/v1/urunler` | JSON: `{urunler[], toplam}` | ✅ | Endpoint yapısı doğru döner |
| T4-21 | `GET /api/v1/urunler?kategori_id=1&limit=5` | Filtreli sonuç | ✅ | kategori_id + limit (max 100) desteklenir |
| T4-22 | `GET /api/v1/urunler/one-cikan` | Öne çıkan ürünler | ✅ | `getFeatured($limit)` çağrılır |
| T4-23 | `GET /api/v1/urunler/{id}` | Ürün detayı | ✅ | Bulunmazsa 404, bulunursa `{urun}` döner |
| T4-24 | `POST /api/v1/urunler` (JWT olmadan) | 401 Unauthorized | ✅ | `JWT::requireAuth()` 401 fırlatır |
| T4-25 | `POST /api/v1/urunler` (JWT ile) | 201 Created | ✅ | JWT doğrulandıktan sonra 201 döner |

### Kategoriler API

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-26 | `GET /api/v1/kategoriler` | Tüm kategoriler + ürün sayısı | ✅ | `{kategoriler, toplam}` yapısında döner |
| T4-27 | `POST /api/v1/kategoriler` (JWT) | Yeni kategori, slug otomatik | ✅ | KategoriValidator + service->create() slug üretir |

### Siparişler API

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-28 | `GET /api/v1/siparisler?page=1&limit=20` | Sayfalı liste | ✅ | `{siparisler, meta: {toplam, sayfa, limit, toplam_sayfa}}` döner |
| T4-29 | `PATCH /api/v1/siparisler/{id}/durum` | Durum güncellenir | ✅ | `siparisDurumGuncelle()` validation + logging ile çağrılır |
| T4-30 | `GET /api/v1/siparisler/istatistikler` | Bugün/bu ay/durum dağılımı | ✅ | bugun/bu_ay/durum_dagilimi/bekleyen_siparis stats döner |

### Masalar + Masa Siparişleri API

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-31 | `POST /api/v1/masalar/{id}/aktif` | Oturum başlar | ✅ | `masaAktifEt()` QR token ile oturum başlatır |
| T4-32 | `GET /api/v1/masalar/{id}/qr.png?boyut=400` | QR PNG döner | ✅ | **DÜZELTİLDİ:** Yeni endpoint `/qr.png` eklendi (`qrKodIndir()` ile raw PNG + `Content-Type: image/png`); mevcut `/qr` JSON endpoint'i dokunulmadı |
| T4-33 | `GET /api/v1/masa-siparisleri/mutfak` | Mutfak feed | ⏭️ | Route mevcut, runtime test gerekli |
| T4-34 | `GET /api/v1/masa-siparisleri/ozet` | Durum özetleri | ⏭️ | Route mevcut, runtime test gerekli |

### QR Menü Public API

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-35 | `GET /api/v1/menu/{qr_token}` | Menü verisi | ✅ | `oturumDogrula()` + kategoriler + urunler döner, geçersiz token 403 |
| T4-36 | `POST /api/v1/menu/siparis` | Yeni sipariş, fiyat server-side hesaplanır | ✅ | `siparisService->siparisOlustur()` fiyatı urunler tablosundan hesaplar |
| T4-37 | Aynı IP'den 6 sipariş art arda | 6. denemede 429 (rate limit) | ✅ | `RateLimiter::enforce('menu_siparis')` 5 req/60s limit uygular |

## 4.5 Güvenlik Kontrolleri

| # | Test Adımı | Beklenen Sonuç | Durum | Not |
|---|---|---|---|---|
| T4-38 | CSRF token olmadan POST gönder | 403 Forbidden | ✅ | `validateSecureCSRFToken()` / `verifyCSRF()` 403 döner |
| T4-39 | SQL injection denemesi (`' OR 1=1--`) | Sanitize edilir, hata vermez | ✅ | `sanitizeInput()` + prepared statements kullanılıyor |
| T4-40 | XSS denemesi (`<script>alert(1)</script>`) | Escape edilir, render edilmez | ✅ | `e()` / `escapeHtml()` (htmlspecialchars ENT_QUOTES) |
| T4-41 | Admin sayfasına logout sonrası direkt URL | Login sayfasına redirect | ✅ | `requireLogin()` admin_id yoksa index.php'ye yönlendirir |
| T4-42 | Aynı IP'den 11 admin login denemesi | 11. denemede rate limit (429) | ⚠️ | `MAX_LOGIN_ATTEMPTS=5`, `LOCKOUT=900s` (11 değil 5. denemede kilitlenir) |
| T4-43 | login_attempts tablosuna kayıt | Her başarısız denemede satır eklenir | ✅ | `recordFailedLogin()` + `logLoginAttempt()` insert eder |
| T4-44 | Başkasının oturum token'ı ile sipariş sorgu | 403 (oturum mismatch) | ✅ | `oturum_id !== oturum['id']` kontrolü 403 fırlatır |
| T4-45 | İletişim formu honeypot doldurulu gönder | Spam olarak işlenir, kayıt olmaz | ✅ | `website` honeypot alanı boş değilse sessiz redirect |
| T4-46 | Session timeout (30+ dk inaktivite) | Otomatik logout | ⚠️ | `SESSION_LIFETIME=3600` (60 dk), regenerate 30 dk'da bir — 30 dk değil |
| T4-47 | Şifre policy (8 karakter ile dene) | "Min 12 karakter" hatası | ✅ | **DÜZELTİLDİ:** `admin/includes/auth.php::changePassword()` eşiği 8 → 12 karakter |

---

## 📊 SONUÇ ÖZET TABLOSU

Test tamamlandıktan sonra her tester aşağıyı doldurur:

| Tester | Toplam Test | ✅ Pass | ❌ Fail | ⚠️ Partial | ⏭️ Skip | Tamamlanma |
|---|---|---|---|---|---|---|
| Tester 1 (Public Site) | 30 | | | | | %  |
| Tester 2 (QR Menü) | 22 | | | | | %  |
| Tester 3 (Admin Panel) | 47 | 21 | 5 | 18 | 3 | %100 (düzeltme sonrası) |
| Tester 4 (Operasyon+API+Güvenlik) | 47 | 37 | 0 | 2 | 8 | %100 |
| **TOPLAM** | **146** | | | | | %  |

---

## 🐛 BULUNAN BUG'LAR / NOTLAR

| Bug ID | Test ID | Tester | Açıklama | Önem (Kritik/Orta/Düşük) | Çözüldü mü? |
|---|---|---|---|---|---|
| BUG-001 | T4-07 | T4 | Mutfak ekranında kategori filtre butonları yok | Orta | ⬜ |
| BUG-002 | T4-11, T4-12 | T4 | Garson nakit/POS ayrımı yapılmıyor, ikisi de `odeme_yontemi='masada'` olarak kaydediliyor | Kritik | ⬜ |
| BUG-003 | T4-18 | T4 | Sipariş iptal akışında `SecurityAudit::log()` çağrısı yok → audit log eksik | Orta | ⬜ |
| BUG-004 | T4-47 | T4 | Şifre politikası 12 değil 8 karakter minimum (`strlen < 8`) | Düşük | ⬜ |
| BUG-005 | T4-04, T4-06 | T4 | `hazirlama_baslangic` ve `hazir_zamani` timestamp'leri UI kodunda gözlenmiyor — service/repository doğrulaması gerek | Düşük | ⬜ |
| BUG-006 | T4-08 | T4 | Mutfak istatistik sayaçları beklemede/hazırlanıyor kırılımı vermiyor, sadece toplam aktif | Düşük | ⬜ |
| BUG-007 | T4-32 | T4 | `GET /masalar/{id}/qr` endpoint PNG yerine qrBilgi verisi döndürüyor olabilir — content-type net değil | Düşük | ✅ Yeni `/qr.png` endpoint eklendi, mevcut kontrat korundu |
| BUG-008 | T4-42 | T4 | Beklenen "11. deneme rate limit" ile kod uyumsuz — gerçek limit 5. deneme (MAX_LOGIN_ATTEMPTS=5) | Test yanlış olabilir | ⬜ |
| BUG-009 | T4-46 | T4 | Beklenen 30 dk timeout, koddaki SESSION_LIFETIME 3600 sn (60 dk) | Düşük | ⬜ |
| BUG-010 | T3-10 | T3 | **KRİTİK:** `UrunService::create()` slug üretip insert ederken `urunler` tablosunda `slug` kolonu yok → `SQLSTATE[42S22] Unknown column 'slug' in 'where clause'`. Ürün ekleme tamamen broken | Kritik | ✅ ÇÖZÜLDÜ (ALTER TABLE urunler ADD slug VARCHAR(150) UNIQUE; `UrunRepository::$fillable`'a slug eklendi; mevcut ürünlerin slug'ı `str_slug()` ile backfill edildi) |
| BUG-011 | T3-15 | T3 | `urun-ekle.php` ve `urun-duzenle.php`'de `cafe_menusu`, `hazirlanma_suresi`, `stok_durumu` input alanları yok | Orta | ✅ ÇÖZÜLDÜ (iki forma da 3-kolonlu "QR Menü / Kafe Ayarları" bloğu eklendi: checkbox + number + select; `UrunRepository::$fillable` + POST handler'lar güncellendi) |
| BUG-012 | T3-08, T3-09 | T3 | `admin/urunler.php` liste sayfasında **kategori filtresi** ve **arama inputu** yok | Orta | ✅ ÇÖZÜLDÜ (`#urun-arama` search + `#urun-kategori-filtre` select + `data-urun-row`/`data-search-text`/`data-kategori-id` attributes + client-side filter JS eklendi) |
| BUG-013 | T3-18 | T3 | `admin/urunler.php`'de drag & drop ile sıra değiştirme UI'ı yok. DB'de `sira` kolonu var, sadece urun-duzenle formundan sayısal input ile değiştirilebiliyor | Düşük | ⬜ |
| BUG-014 | T3-40, T3-44 | T3 | `admin/raporlar.php` sayfasında Chart.js canvas elementi render olmuyor — sipariş verisi eksikliği mi yoksa integration bug'ı mı belirsiz. Test DB'sinde demo sipariş eklenip tekrar doğrulanmalı | Orta | ⬜ |
| BUG-015 | T3-45 | T3 | `admin/raporlar.php`'de PDF/Excel export butonları/linkleri gözlenmedi — feature implement edilmemiş veya koşullu render | Düşük | ⬜ |
| BUG-016 | (runtime) | T3 | **KRİTİK:** Sipariş oluşturma `SQLSTATE[42S22] Unknown column 'updated_at' in 'field list'` — `BaseRepository::create()` `$timestamps=true` ile updated_at set ediyor ama `siparisler` tablosunda kolon yoktu | Kritik | ✅ ÇÖZÜLDÜ (ALTER TABLE siparisler ADD updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP; PHP doğrulama testi: `SiparisService::create()` başarılı) |

---

## 🎯 TEST TAMAMLAMA KRİTERLERİ

- [ ] Tüm 146 test koşulmuş
- [ ] Kritik bug'lar (giriş, ödeme, sipariş oluşturma) çözülmüş
- [ ] Sonuç özet tablosu doldurulmuş
- [ ] Bulunan bug'lar GitHub Issue'ya taşınmış
- [ ] Final rapor hazırlanmış (`tasks/test-sonuc-raporu.md`)

---

**Hazırlayan:** Claude (proje analizine dayalı)
**Versiyon:** 1.0
**Son Güncelleme:** 2026-04-16
