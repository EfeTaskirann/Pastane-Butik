# KKTC Gazimağusa — Pastane Projesi Geçim Sağlama Analizi

**Tarih:** 2026-04-18
**Kapsam:** Bu yazılım projesi baz alınarak KKTC Gazimağusa'da ticari sürdürülebilirlik
**Uyarı:** Rakamlar KKTC 2026 başı genel bilgiye dayalı tahminlerdir; yerel muhasebeci + saha araştırması ile doğrulanmalıdır.

---

## Özet (TL;DR)

**Cevap: EVET, geçim sağlanabilir — ama senaryo seçimi kritik.**

| Senaryo | Başlangıç Sermayesi | Yıl 2 Net Gelir/Ay | Risk | Başabaş |
|---------|---------------------|--------------------|----- |---------|
| **A) Kendi pastaneni işlet** | ₺1.1M–₺1.9M | ₺60K–₺180K | Yüksek | 8–14 ay |
| **B) SaaS/lisans satışı** | ₺150K–₺300K | ₺20K–₺60K | Orta-Yüksek | 14–24 ay |
| **C) Hibrit (pastane + SaaS)** | ₺1.3M–₺2.1M | ₺80K–₺230K | Yüksek | 10–16 ay |

**En iyi risk/getiri oranı: Senaryo C (Hibrit)** — kendi pastanesini "showroom" olarak kullanıp yazılımı aynı pazara sat.

---

## 1. Projenin Değerlendirmesi

### 1.1 Teknik güç (satmaya hazır olanlar)
- 5 sprint tamamlanmış, **181/181 PHPUnit pass**, PHPStan 0 error, PSR-12 temiz
- **Production-ready altyapı**: Blue-green deploy, Prometheus, Sentry, backup, incident runbook
- **Kapsam genişliği**: QR menü, masa/garson/mutfak, RBAC+2FA, email/SMS, sadakat, raporlar, activity log, dark mode, WCAG AA, 25 endpoint OpenAPI dokümante
- **Türkçe-native** (KKTC için ideal, lokalize gereksinimi yok)
- Mimari temizliği (Controller/Service/Repository, Base abstractions) → özelleştirme kolay

### 1.2 Ticarileşme için eksikler (kritik)
| Eksik | Etki | Tahmini Tamamlanma |
|-------|------|---------------------|
| **KKTC sanal POS entegrasyonu** (Asbank, Near East, Creditwest) | Olmadan SAT OLMAZ | 3–6 hafta |
| **Multi-tenancy** (çok-müşteri DB/subdomain isolation) | SaaS modeli için zorunlu | 6–10 hafta |
| **Çok şubeli işletme desteği** | Zincirler için | 4–6 hafta |
| **Stok/malzeme maliyeti modülü** | Kâr analizi için eksik | 3–4 hafta |
| **e-Fatura / KKTV integrasyonu** | KKTC'de henüz zorunlu değil ama geliyor | 4–8 hafta |
| **Müşteri onboarding akışı** | Satış sürtünmesi | 2–3 hafta |

**Toplam ek geliştirme (SaaS için):** 4–6 ay tam zamanlı çalışma.

### 1.3 Pastane operasyonu için doğrudan değeri
- Sıfır POS lisans maliyeti (rakipler Adisyo/Günyazılım için ₺500–₺2,000/ay öder)
- Verimlilik artışı: hatalı sipariş azalır, stok-fire takibi, raporla ürün portföy optimizasyonu
- **Yazılım katkısı net kâr marjında tahmini %5–%10** (orta ölçekli pastanede ₺10K–₺25K/ay değer)
- Ama: Yazılım pastaneyi başarılı yapmaz. Konum + ürün + servis asıldır.

---

## 2. KKTC Gazimağusa Pazar Gerçekleri

### 2.1 Demografi
- Gazimağusa nüfusu: ~55–65k merkez, ~90–100k metropolitan
- **EMU (DAÜ)**: ~18–22k öğrenci (Eylül–Haziran aktif, yaz düşer)
- Turist akışı: Mayıs–Eylül yoğun (Türkiye kaynaklı tour/günübirlik)
- Kıbrıslı Türk topluluğu: Yüksek iç sosyal ağ — "ağızdan ağıza" en güçlü pazarlama

### 2.2 Ekonomik durum (2026 Q1 itibariyle)
- **TL volatilitesi yüksek** → ithal bağımlı işletme için kâr aşınması
- Unsuz bağımlı ürünler (çikolata, süt tozu, kakao, bazı meyveler) ağırlıklı ithalat
- Asgari ücret 2026 beklenti: ~₺18–20K/ay (Türkiye civarı, KKTC biraz daha düşük)
- Kira: Merkez yeme-içme 80–150 m² için ₺25K–₺60K/ay (Salamis Yolu, Lefkoşa Yolu pahalı; Kaleiçi daha ekonomik)
- Vergi: KKTV %18, kurumlar vergisi %15–25

### 2.3 Rekabet
- **Güçlü oyuncular**: Çamlıbel, Çetinkaya, Lokum, Süt Hakkı, Gloria Jean's benzeri kafe zincirleri
- **Bağımsız pastaneler**: 20–30 civarı küçük/orta işletme
- **Yazılım penetrasyonu**: Düşük. Çoğu adisyon defteri + WhatsApp + basit POS
- **Niş fırsatları**: Vegan/glutensiz, özel pasta siparişi, kurumsal catering, cuma-cumartesi aile masası

---

## 3. Senaryo A — Kendi Pastaneni İşletmek

### 3.1 Sermaye ihtiyacı
| Kalem | Tutar (TL) |
|-------|-----------|
| Kira depozito + 3 ay peşin | 150,000 – 240,000 |
| Tadilat + tefrişat (modern kafe/pastane) | 250,000 – 500,000 |
| Ekipman (fırın, buzdolabı, vitrin, espresso, kombine) | 400,000 – 700,000 |
| İlk stok + logo + açılış pazarlaması | 80,000 – 150,000 |
| 3 aylık işletme sermayesi tamponu | 300,000 – 500,000 |
| **Toplam** | **₺1,180,000 – ₺2,090,000** |

### 3.2 Aylık operasyon maliyeti (gerçekçi)
| Kalem | Tutar (TL) |
|-------|-----------|
| Kira | 30,000 – 50,000 |
| Personel (3–4 kişi, patron dahil değil) | 60,000 – 85,000 |
| Utilities (elektrik/su/doğalgaz/internet) | 12,000 – 20,000 |
| Muhasebe + vergi danışmanı | 6,000 – 10,000 |
| Pazarlama + küçük giderler | 8,000 – 15,000 |
| **Sabit toplam** | **116,000 – 180,000** |
| + COGS (ciro × %35) | değişken |

### 3.3 Ciro beklentisi
- Sepet ortalaması: ₺150–₺280 (pasta + içecek karma)
- Günlük müşteri: 60–180 (konum ve sezona bağlı)
- **İyi konumda gerçekçi aylık ciro: ₺400K–₺700K**
- Muhafazakâr (yeni açılış, hype olmayan): ₺200K–₺350K
- Başarılı işletme: ₺700K–₺1.2M

### 3.4 Net kâr senaryoları
| Ciro/Ay | COGS (%35) | Sabit Gider | Net Kâr | Durum |
|---------|-----------|-------------|---------|-------|
| ₺200K | ₺70K | ₺150K | **-₺20K** | Zarar — kapatma sinyali |
| ₺300K | ₺105K | ₺150K | **₺45K** | Sınırda geçim |
| ₺500K | ₺175K | ₺150K | **₺175K** | İyi geçim |
| ₺700K | ₺245K | ₺160K | **₺295K** | Üstün başarı |

### 3.5 Mevsimsellik
- **Yaz (Haz–Ağu)**: Öğrenciler gider → ciroda %30–50 düşüş, turist kısmen telafi eder
- **Eylül**: Toparlanma, okul açılışı peak
- **Kasım–Mart**: Zirve (öğrenci + yerli + sınav stresi = pasta/kahve talebi)

### 3.6 Risk
- **TL şoku** (ithal malzeme): En büyük risk. %30+ devalüasyon = 3 ay kârsız
- **Personel devri**: Yüksek — çift vardiya ve kültür yatırımı gerekir
- **Rekabet**: Yakınına yeni Çamlıbel/Lokum şubesi açılırsa ciro %20+ düşer
- **Kira artışı**: Sözleşme dışı enflasyon zamları agresif olabilir

---

## 4. Senaryo B — Yazılımı SaaS/Lisans Olarak Satmak

### 4.1 Pazar büyüklüğü
| Bölge | Potansiyel Müşteri | Realist Pen. %15–25 | Ulaşılabilir |
|-------|---------------------|---------------------|--------------|
| Gazimağusa | 30–60 | 5–15 | 2 yıl |
| Tüm KKTC | 400–600 | 15–40 | 3 yıl |
| + Güney Kıbrıs (potansiyel) | 200–400 | 5–20 | 4+ yıl (dil bariyeri) |

### 4.2 Fiyatlama (Türkiye Adisyo/Logo referans)
| Paket | Fiyat/Ay | Hedef |
|-------|----------|-------|
| Starter (tek masa, ≤10 masa) | ₺1,200–₺1,800 | Küçük kafe |
| Professional (10–25 masa, QR, garson) | ₺2,000–₺3,500 | Orta pastane |
| Enterprise (25+ masa, çoklu şube, API) | ₺4,000–₺7,000 | Zincir |
| Kurulum peşin (opsiyonel) | ₺15K–₺40K | İlk ay |

### 4.3 Gelir projeksiyonu (muhafazakâr)
| Dönem | Aktif Müşteri | Aylık MRR | Net Kâr/Ay |
|-------|---------------|-----------|------------|
| Yıl 1 Q1–Q2 | 0–2 | ₺0–₺5K | **Zarar** (pazarlama, kuruluş) |
| Yıl 1 Q3–Q4 | 3–6 | ₺7K–₺18K | Başabaş |
| Yıl 2 Q1–Q2 | 7–12 | ₺16K–₺35K | ₺5K–₺18K |
| Yıl 2 Q3–Q4 | 12–20 | ₺25K–₺55K | ₺12K–₺35K |
| Yıl 3 | 25–40 | ₺55K–₺110K | ₺30K–₺70K |

### 4.4 Maliyetler
| Kalem | Aylık (TL) |
|-------|-----------|
| VDS hosting + yedek (Hetzner/Linode) | 2,500 – 4,500 |
| SMS gateway (Twilio/Netgsm) | 1,000 – 2,500 |
| E-posta servisi | 300 – 800 |
| Domain, SSL, sertifikalar | 300 – 500 |
| Muhasebe KKTC şirket | 2,500 – 4,000 |
| Pazarlama (ilk 12 ay) | 5,000 – 12,000 |
| **Toplam sabit** | **11,600 – 24,300** |
| + Destek zamanı (10–15 saat/hafta — opportunity cost) | — |

### 4.5 Kritik başarı faktörleri
1. **Referans müşteri** (ilk 1–2): Şehir içinde görünür, saygın bir işletme → domino etkisi yapar
2. **Saha satışı**: KKTC'de online kapanış zor. Kapı kapı demo + fincan kahveler
3. **Yüz yüze ilişki**: 3–4 ay ilişki kurmadan sözleşme imzalatmak zor
4. **Destek hızı**: Kritik bug'a 2 saat içinde cevap zorunlu (itibar)
5. **WhatsApp hattı**: Sipariş alınır; teknik destek soruları müşteriden gelir — 7/14 erişilebilir olmalı

### 4.6 Zorluklar
- İlk 6 ay muhtemelen 0 satış. Referans + güven + demo yok.
- Geleneksel esnaf yazılıma ödemek istemez, özellikle bilinmeyen birinden
- Aylık ödeme topluluk kültürüne uzak — havale/EFT, aylık kovalama iş yükü yüksek
- Ücretsiz deneme verirsen bir kez bulunca asla ödemeye geçmeyen müşteri olur

---

## 5. Senaryo C — Hibrit (Pastane + SaaS)

### 5.1 Strateji
1. **Faz 1 (0–6 ay)**: Kendi pastaneyi aç. Yazılımı in-house kullan ve battle-test et.
2. **Faz 2 (6–12 ay)**: İşletme stabilize olunca ilk 2–3 yan müşteriye demo et (indirimli pilot).
3. **Faz 3 (12+ ay)**: Kendi pastanen referans. Yazılımı saymaca satmaya başla.

### 5.2 Avantajlar
- Pastane nakit akışı = SaaS büyüme finansmanı
- Gerçek kullanıcı feedback'i = yazılım kalitesi
- En güçlü satış pitch'i: "Ben de kullanıyorum, 6 aydır. Gel şubemi gör."
- İki bacak = tek şoka dayanıklılık

### 5.3 Gelir (yıl 2 sonu, iyimser)
- Pastane net: ₺60K–₺150K/ay
- SaaS net: ₺20K–₺50K/ay
- **Toplam: ₺80K–₺200K/ay**

### 5.4 Zorluk
- İki ayrı kompleks iş: dikkat dağılması risk
- Sermaye bağlanması yüksek
- Çift vardiya/hafta sonu baskısı

---

## 6. Risk Matrisi

| Risk | Olasılık | Etki | Azaltma |
|------|----------|------|---------|
| TL devalüasyonu %30+ | Yüksek | Yüksek | €/$ bazlı stok, 3 aylık fiyat revizyonu hakkı |
| Personel kayıpları | Yüksek | Orta-Yüksek | Öğrenci istihdamı, kültür, çift vardiya |
| Yeni rakip açılması | Orta | Orta | Niş konumlanma (vegan/catering) |
| Yazılımda kritik bug | Orta | Yüksek (itibar) | Test suite, monitoring, incident runbook kullan |
| Müşteri ödeme temerrüdü (B/C) | Yüksek | Orta | Peşin 3 ay + oto-kesinti kontratla |
| Sermaye yetersizliği | Orta | Kritik | Faz 1 minimum bütçe; kredi hattı hazır |
| KKTC regülasyon değişikliği (e-fatura/KKTV) | Düşük-Orta | Orta | Yerel muhasebeciyle sürekli iletişim |
| Öğrenci yaz göçü | Kesin | Yüksek | Turist odaklı yaz menüsü, catering |

---

## 7. Kritik Karar Kriterleri

### Kendi durumunuzu değerlendirin:

| Soru | Evet ise | Hayır ise |
|------|----------|-----------|
| ₺1M+ sermayen var mı? | Senaryo A veya C mümkün | Senaryo B düşün |
| Pastacılık/gastronomi deneyimin var mı? | Senaryo A risk düşer | Ortak/şef kirala veya Senaryo B |
| 7/24 operasyon stresini kaldırabilir misin? | Senaryo A/C | Senaryo B |
| Saha satış + yüz yüze müşteri ilişkisi kurmayı sevebilir misin? | Senaryo B/C | Senaryo A |
| Gazimağusa'da yerleşik misin, yerel ağın var mı? | Her şey kolaylaşır | Önce 6 ay ikamet + ağ kur |
| 12–18 ay gelir sıfır olabilir, dayanabilir misin? | Senaryo B viable | Senaryo A veya C (6 ay içinde gelir) |

---

## 8. Somut Sonraki Adımlar (2–4 hafta)

### 8.1 Pazar doğrulama (kritik, düşük maliyet)
- [ ] **10–15 pastane sahibiyle yüz yüze konuş** (Gazimağusa merkez). Sor:
  - "En büyük operasyonel sıkıntın ne?" (stok? personel? sipariş takibi?)
  - "Şu an hangi POS kullanıyorsun?" (Adisyo? elle?)
  - "Aylık 2,500 TL öder miydin böyle bir sistem için?" (reaksiyon oku)
- [ ] 3 pastane ile ücretsiz pilot anlaşması dene
- [ ] EMU kampüs içi veya öğrenci sitesi kafe sahipleriyle konuş (teknoloji daha açık)

### 8.2 Finansal net (2 hafta)
- [ ] 5 farklı lokasyon için kira + aidat fiyatlarını somut al
- [ ] 2. el ekipman satıcıları: Facebook KKTC grupları, Dolap.com KKTC
- [ ] KKTC bankalarından ticari kredi görüşmesi (Asbank, Near East, Türk Kooperatif)
- [ ] KKTV + kurumlar vergisi için KKTC yerleşik muhasebeciyle ilk toplantı

### 8.3 Yazılım hazırlığı (Senaryo B/C için)
1. **Multi-tenancy refactor** (6–10 hafta):
   - `tenant_id` kolonu her tabloya
   - Subdomain routing: `firma1.pastane-app.com`
   - Tenant izolasyonu test
2. **Sanal POS entegrasyonu** (3–6 hafta):
   - Creditwest API (en yaygın KKTC POS sağlayıcısı)
   - 3D Secure flow
   - İade/refund akışı
3. **Onboarding akışı** (2–3 hafta):
   - "Hesap aç → menü içe aktar → masalar kurul → ilk sipariş" 1 saatte
4. **Türkçe dokümantasyon + demo videoları** (2 hafta)

### 8.4 Karar matrisi (tüm araştırma bitince)
- Sermaye < ₺300K + online satış sevmiyorsan → **bu projeyi ASIL iş yapma**, freelance/maaşlı işle destekle, 1–2 yıl birikim yap
- Sermaye < ₺300K + saha satışı yapabiliyorsan → **Senaryo B** (salt SaaS, 18 ay sabır gerekir)
- Sermaye ₺1M+ + gastronomi ilgin yok → **Senaryo B** (ortak kiralamadan güçlü değil)
- Sermaye ₺1M+ + pastacılık merak ediyorsun → **Senaryo C** (hibrit, en güçlü)
- Sermaye ₺1.5M+ + ekip kurabiliyorsan → **Senaryo C + ölçekleme** (yıl 3'te Lefkoşa/Girne şube)

---

## 9. Dürüst Kapanış Notu

**KKTC küçük bir pazar.** Bu yazılımla tek başına ₺1M+/ay kazanmak zor — hem pazar ölçeği, hem kültür (yazılıma ödeme direnci) nedeniyle.

**Ama rahat geçim (₺80K–₺200K/ay) kesinlikle mümkün** — doğru senaryoyla ve 18–24 ay sabırla.

**En büyük 3 tavsiye:**
1. **Önce kendi pastanen** — yazılım olmadan da kazanır, yazılım kaymağıdır. Referans olur.
2. **Öğrenci niş'i** — EMU öğrenci odaklı QR menü + sadakat + WhatsApp sipariş, bu projen buna bire bir uyuyor
3. **Türkiye ölçeklenmesi** — KKTC'de başarı yakalarsan Antalya/Mersin/Adana gibi üniversite şehirlerine taşıması kolay; asıl büyüme oralarda

**Yapma tavsiyeleri:**
- Salt yazılım sat (Senaryo B) ama saha satışı sevmiyorsan → ilerleyemezsin
- Tek müşteriye bağımlı olma — 1 müşteri <%20 MRR kuralı
- İlk yıl kâr bekleme, sabret
- "Mükemmel yazılım yapayım sonra satarım" tuzağı — MVP sat, iterate et

**Son soru kendine sor:** "Gerçekten pastane sektöründe mi olmak istiyorum, yoksa yazılım geliştirmek mi?" Cevap farklıysa, farklı projeler düşün.

---

**Rapor yazarı:** Claude Opus 4.7
**Rakamlar:** Tahmin — yerel saha araştırmasıyla doğrulanmalı
**Versiyon:** v1.0 — KKTC Gazimağusa 2026 Q2 odaklı
