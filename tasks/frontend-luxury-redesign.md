# TASK: Frontend Lüks & Erişilebilir Yeniden Tasarım

**Tarih:** 2026-03-31
**Öncelik:** Yüksek
**Kapsam:** Winter/Summer tema görsel iyileştirme, background image, lüks hissiyat

---

## 1. MEVCUT DURUM ANALİZİ
## Prosedür Disiplini

Bir script, checklist veya kural seti verildiğinde:
1. Önce TÜM adımları listele — "heyecanlı" ve "idari" ayrımı yapma
2. Her adımı tamamladığında açıkça işaretle
3. "Tamamlandı" demeden önce checklist'e geri dön, atlanan adım var mı kontrol et
4. Kuralların hangi kısmının önemli olduğuna sen karar verme — hepsine uy
5. Bir adımı atladıysan, kullanıcı sormadan önce kendin fark et ve düzelt

### 1.1 Güçlü Yanlar
- CSS variable tabanlı temiz tema mimarisi (`data-theme="kis"` / `data-theme="yaz"`)
- Winter: Kar yağışı animasyonu, köpek kızağı sahnesi, Olaf benzeri kardan adam SVG
- Summer: Yüzen meyve animasyonu, güneş ışınları efekti
- Playfair Display + Poppins font kombinasyonu (lüks hissiyat için iyi temel)
- SVG tabanlı illüstrasyonlar (pasta, cupcake, teslimat kamyonu)
- Reveal animasyonları ve smooth scroll

### 1.2 Kritik Eksiklikler

#### A. Background Image YOK
- **Hiçbir temada gerçek background image kullanılmıyor**
- Tüm arka planlar düz CSS gradient (pembe→krem, krem→pembe)
- Sectionlar arası geçişlerde görsel derinlik yok
- Hero section sadece SVG pasta + gradient — ilk izlenim zayıf

#### B. İlk İzlenim (First Impression) Zayıf
- Hero section 100vh ama sadece başlık + SVG pasta — "WOW" etkisi yok
- Markanın lüks hissiyatı renk paletinden ibaret, görsel katman eksik
- Sayfaya girdiğinde "butik pastane" değil "basit web sitesi" hissi veriyor
- Promo banner (öğrenci indirimi) ilk gördüğün şey — lüks hissiyatı hemen kırıyor.Öğrenci indirimi banner'ı aktif-inaktif şekilde ayarlanmalı.

#### C. Temalar Arası Görsel Fark Yetersiz
- Winter ve Summer arasındaki fark sadece renk paleti değişimi
- Background texture/pattern/image farklılaşması yok
- Her iki tema da aynı layout, aynı gradientler — sadece ton değişiyor
- Animasyonlar farklı (kar vs meyve) ama arka plan aynı his

#### D. Derinlik ve Katmanlama Eksik
- Parallax efekti animations.css'de tanımlı ama kullanılmıyor
- Section geçişlerinde sadece gradient var, texture yok
- Floating elementler var ama arka planda katman hissi yok
- Glassmorphism sadece winter kartlarda — genel tasarıma yayılmamış

#### E. Lüks Tasarım Kodları Eksik
- Gold/altın accent rengi sadece winter'da var (`--winter-gold: #D4A574`) — ana temada yok
- Subtle pattern/texture overlay hiç yok
- Luxury typography hierarchy zayıf (başlıklar yeterince büyük/etkileyici değil)
- Micro-interaction'lar sınırlı (hover efektleri basit lift+shadow)

---

## 2. HEDEFLENENVİZYON

**Hissiyat:** "Lüks butik pastane — erişilebilir fiyatlarla"
**İlk izlenim:** Sayfaya girdiğinde "Vay be, burası ciddi bir yer" dedirtmeli
**Tema farkı:** Winter ve Summer tamamen farklı dünyalar gibi hissettirmeli

### Referans Anahtar Kelimeler
- Luxury bakery website
- Boutique patisserie
- Warm elegance
- Premium yet welcoming
- Seasonal immersion

---

## 3. EYLEM PLANI

### FAZ A: Background Image Sistemi (Kritik)

#### A1. Hero Section Background Images
- [ ] **Winter Hero:** Karlı pencere önünde sıcak pastane görüntüsü
  - Buğulu cam efekti overlay (CSS backdrop-filter)
  - Sıcak ışık tonları (iç mekan vs dış kar kontrast)
  - Subtle snow texture overlay
  - Önerilen: Fotoğrafik ya da yüksek kalite AI-generated görsel
  - Boyut: 1920x1080 min, WebP format, lazy-load yok (hero critical)

- [ ] **Summer Hero:** Açık hava teras/bahçe pastane ortamı
  - Güneş ışığı lens flare efekti (CSS pseudo-element)
  - Sıcak, davetkar yaz atmosferi
  - Çiçekli/yeşillikli çerçeve
  - Önerilen: Parlak, aydınlık, canlı renk tonları

- [ ] **Default Hero:** Şık, nötr pastane iç mekan
  - Mermer tezgah + pastalar
  - Soft pembe/krem tonları doğal olarak
  - Zamansız elegance

#### A2. Section Background Textures
- [ ] **Winter textures:**
  - Buzlu cam texture (sections arası)
  - Subtle kar tanesi pattern (SVG repeating)
  - Sıcak ahşap doku (about section)
  - Buğu/frost kenar efekti

- [ ] **Summer textures:**
  - Hafif linen/keten doku
  - Subtle çiçek pattern (very muted)
  - Güneş ışığı bokeh overlay
  - Taze meyve watercolor splash

- [ ] **Default textures:**
  - Mermer/marble subtle pattern
  - Soft fabric texture
  - Minimal geometric pattern

#### A3. Parallax Background Katmanları
- [ ] Hero section: 3 katmanlı parallax (arka plan, orta dekor, ön plan içerik)
- [ ] About section: Hafif parallax efekti
- [ ] Product section: Subtle texture parallax

### FAZ B: Lüks Hissiyat Elementleri

#### B1. Hero Section Yeniden Tasarım
- [ ] Full-screen hero image + overlay gradient
- [ ] Başlık tipografisi büyütme: `clamp(3rem, 8vw, 6rem)` → daha dramatik
- [ ] Altın (gold) accent çizgi/divider başlık altında
- [ ] Subtle particle efekti (tema bazlı — kar/güneş tozları)
- [ ] Promo banner'ı hero'nun altına taşı (ilk izlenimi bozmasın)
- [ ] "Scroll down" indicator'ı daha elegant yap (ince çizgi + ok)

#### B2. Gold Accent Sistemi (Tüm Temalara)
- [ ] `--gold-primary: #C5A572` (antik altın)
- [ ] `--gold-light: #D4B896` (açık altın)
- [ ] `--gold-shimmer: linear-gradient(135deg, #C5A572, #E8D5B5, #C5A572)`
- [ ] Divider'lar, border accent'ler, icon highlight'lar için
- [ ] Winter: Soğuk altın (#B8A88A), Summer: Sıcak altın (#D4A54A)

#### B3. Glassmorphism & Depth
- [ ] Product kartları: Glassmorphism efekti (tüm temalarda)
- [ ] Section başlıkları: Frosted glass banner
- [ ] Floating CTA butonları: Glass efekti + glow
- [ ] Card hover: Subtle 3D tilt efekti (CSS perspective)

#### B4. Micro-Interactions
- [ ] Buton hover: Shimmer sweep efekti (altın dalga)
- [ ] Product kart hover: Image zoom + overlay gradient
- [ ] Link hover: Altın underline animasyonu
- [ ] Section giriş: Staggered reveal (mevcut ama daha dramatik)
- [ ] Scroll-triggered number counter (istatistik section için)

### FAZ C: Tema Farklılaştırma (Derinlik)

#### C1. Winter Tema Derinleştirme
- [ ] Hero: Karlı dış mekan + sıcak pastane iç mekan kontrast görseli
- [ ] Sections: Buz kristali border pattern
- [ ] Product kartları: Frost kenar efekti (hover'da erir animasyonu)
- [ ] Footer: Karla kaplı şehir silueti
- [ ] Genel atmosfer: Sıcak iç mekan, soğuk dış mekan kontrast
- [ ] Renk derinliği: Koyu mavi-gri arka plan + sıcak iç mekan aydınlatma

#### C2. Summer Tema Derinleştirme
- [ ] Hero: Açık hava teras + pastane vitrini görseli
- [ ] Sections: Watercolor çiçek kenar deseni
- [ ] Product kartları: Güneş ışığı spot efekti (hover)
- [ ] Footer: Yaz akşamı silueti (palmiye/ağaç)
- [ ] Genel atmosfer: Taze, canlı, enerjik ama elegant
- [ ] Renk derinliği: Parlak ama pastel — göz yormayan sıcaklık

#### C3. Tema Geçiş Efektleri
- [ ] Tema değiştiğinde smooth color transition (CSS transition 0.5s)
- [ ] Background image crossfade
- [ ] Dekoratif elementlerin morph animasyonu

### FAZ D: Tipografi & Spacing Refinement

#### D1. Başlık Hiyerarşisi
- [ ] H1 (Hero): `clamp(2.5rem, 7vw, 5.5rem)` — büyük, dikkat çekici
- [ ] H2 (Section): `clamp(1.8rem, 4vw, 3rem)` — net, okunabilir
- [ ] Başlık altı ince altın çizgi (divider)
- [ ] Letter-spacing artırımı başlıklarda (0.02em → 0.05em)

#### D2. Whitespace & Breathing Room
- [ ] Section padding artırımı: `6rem 0` → `8rem 0` (min)
- [ ] Kart arası boşluk: `gap: 1.5rem` → `gap: 2rem`
- [ ] Başlık-içerik arası boşluk artırımı
- [ ] Lüks tasarımda boşluk = nefes alanı = premium his

### FAZ E: Performans & Teknik

#### E1. Image Optimizasyon
- [ ] WebP format (fallback JPG)
- [ ] `<picture>` element ile responsive images
- [ ] Hero image: preload + priority hint
- [ ] Section backgrounds: lazy-load (Intersection Observer)
- [ ] Blur placeholder (LQIP) — düşük kalite önizleme

#### E2. Animasyon Performansı
- [ ] `will-change` property kullanımı (parallax elementler)
- [ ] `transform` ve `opacity` dışında animasyon yok (GPU acceleration)
- [ ] `prefers-reduced-motion` kontrolü (mevcut, genişlet)
- [ ] Mobile'da parallax devre dışı (performans)

---

## 4. ÖNCELİK SIRASI

| Sıra | Görev | Etki | Efor |
|------|-------|------|------|
| 1 | Hero background images (3 tema) | ⭐⭐⭐⭐⭐ | Orta |
| 2 | Gold accent sistemi | ⭐⭐⭐⭐ | Düşük |
| 3 | Hero section yeniden tasarım | ⭐⭐⭐⭐⭐ | Orta |
| 4 | Section background textures | ⭐⭐⭐⭐ | Orta |
| 5 | Glassmorphism genişletme | ⭐⭐⭐ | Düşük |
| 6 | Parallax katmanları | ⭐⭐⭐ | Orta |
| 7 | Micro-interactions | ⭐⭐⭐ | Düşük |
| 8 | Tipografi refinement | ⭐⭐⭐ | Düşük |
| 9 | Tema derinleştirme (Winter) | ⭐⭐⭐⭐ | Yüksek |
| 10 | Tema derinleştirme (Summer) | ⭐⭐⭐⭐ | Yüksek |
| 11 | Image optimizasyon | ⭐⭐ | Düşük |

---

## 5. BACKGROUND IMAGE STRATEJİSİ

### Dosya Yapısı
```
assets/
└── images/
    └── backgrounds/
        ├── hero/
        │   ├── hero-default.webp      (1920x1080)
        │   ├── hero-default-mobile.webp (768x1024)
        │   ├── hero-winter.webp        (1920x1080)
        │   ├── hero-winter-mobile.webp  (768x1024)
        │   ├── hero-summer.webp        (1920x1080)
        │   └── hero-summer-mobile.webp  (768x1024)
        ├── sections/
        │   ├── about-bg.webp
        │   ├── products-bg.webp
        │   └── contact-bg.webp
        └── textures/
            ├── marble-subtle.webp
            ├── frost-pattern.svg
            ├── linen-texture.webp
            ├── bokeh-warm.webp
            └── snowflake-pattern.svg
```

### Image Kaynağı Önerileri
1. **AI-Generated** (Midjourney/DALL-E/Flux): Tamamen özel, marka uyumlu
2. **Stock Photo** (Unsplash/Pexels): Ücretsiz, yüksek kalite
3. **Hibrit**: Stock fotoğraf + Photoshop/CSS overlay ile markaya uyarlama

### Hero Image Overlay CSS Stratejisi
```css
/* Temel yapı */
.hero-section {
    position: relative;
    background-size: cover;
    background-position: center;
    background-attachment: fixed; /* Parallax */
}

.hero-section::before {
    content: '';
    position: absolute;
    inset: 0;
    /* Tema bazlı overlay */
}

/* Winter overlay: Koyu + sıcak vignette */
[data-theme="kis"] .hero-section::before {
    background: linear-gradient(
        180deg,
        rgba(30, 40, 60, 0.5) 0%,
        rgba(30, 40, 60, 0.3) 50%,
        rgba(30, 40, 60, 0.7) 100%
    );
}

/* Summer overlay: Parlak + sıcak */
[data-theme="yaz"] .hero-section::before {
    background: linear-gradient(
        180deg,
        rgba(255, 240, 220, 0.3) 0%,
        rgba(255, 200, 150, 0.15) 50%,
        rgba(255, 240, 220, 0.4) 100%
    );
}
```

---

## 6. LÜKS HİSSİYAT KONTROL LİSTESİ

Tamamlandığında site şu soruları "EVET" ile yanıtlamalı:

- [ ] İlk 3 saniyede "bu ciddi ve şık bir yer" hissi veriyor mu?
- [ ] Winter ve Summer temaları tamamen farklı dünyalar gibi mi?
- [ ] Background görseller marka hikayesini destekliyor mu?
- [ ] Gold accent'ler lüks hissiyatı güçlendiriyor mu?
- [ ] Whitespace yeterli mi (sıkışık hissetmiyor mu)?
- [ ] Tipografi hiyerarşisi net ve etkileyici mi?
- [ ] Micro-interaction'lar pürüzsüz ve rafine mi?
- [ ] Mobile'da da aynı lüks his var mı?
- [ ] Sayfa performansı hâlâ hızlı mı (LCP < 2.5s)?
- [ ] Accessibility (erişilebilirlik) korunuyor mu?

---

## 7. NOTLAR

- **Image boyutu kritik:** Hero görselleri 200KB altında olmalı (WebP + sıkıştırma)
- **Parallax mobile'da kapalı:** `background-attachment: fixed` iOS'ta sorunlu — mobile'da `scroll` kullan
- **Reduced motion:** Tüm yeni animasyonlar `prefers-reduced-motion` kontrolü içermeli
- **Font yükleme:** Playfair Display zaten preconnect ile yükleniyor — ek font ekleme
- **Tema geçişi:** Mevcut `TemaService` cache'i (5 dk TTL) background image path'lerini de desteklemeli
