<?php
declare(strict_types=1);

/**
 * Turkce ceviriler.
 *
 * Nested array formati — erisim: t('nav.home') → 'Ana Sayfa'
 * Flat dot-key kullanimi da destekleniyor: t('nav.home')
 * Placeholder: t('common.welcome', ['name' => 'Efe']) → "Hos geldin, Efe"
 *
 * KURAL: Her key tam Turkce diakritikli yazilmali (I, S, G, C, U, O).
 *
 * @package Pastane
 */

return [
    // ============================================
    // COMMON — Genel kelimeler ve ifadeler
    // ============================================
    'common' => [
        'welcome'       => 'Hoş geldin, {{name}}',
        'loading'       => 'Yükleniyor...',
        'saving'        => 'Kaydediliyor...',
        'sending'       => 'Gönderiliyor...',
        'yes'           => 'Evet',
        'no'            => 'Hayır',
        'ok'            => 'Tamam',
        'cancel'        => 'İptal',
        'close'         => 'Kapat',
        'back'          => 'Geri',
        'next'          => 'İleri',
        'previous'      => 'Önceki',
        'search'        => 'Ara',
        'filter'        => 'Filtrele',
        'all'           => 'Tümü',
        'none'          => 'Hiçbiri',
        'select'        => 'Seçiniz',
        'required'      => 'Zorunlu',
        'optional'      => 'İsteğe bağlı',
        'edit'          => 'Düzenle',
        'delete'        => 'Sil',
        'update'        => 'Güncelle',
        'create'        => 'Oluştur',
        'add'           => 'Ekle',
        'save'          => 'Kaydet',
        'reset'         => 'Sıfırla',
        'confirm'       => 'Onayla',
        'continue'      => 'Devam Et',
        'show'          => 'Göster',
        'hide'          => 'Gizle',
        'active'        => 'Aktif',
        'inactive'      => 'Pasif',
        'status'        => 'Durum',
        'date'          => 'Tarih',
        'time'          => 'Saat',
        'total'         => 'Toplam',
        'subtotal'      => 'Ara Toplam',
        'price'         => 'Fiyat',
        'quantity'      => 'Adet',
        'note'          => 'Not',
        'notes'         => 'Notlar',
        'details'       => 'Detaylar',
        'more'          => 'Daha Fazla',
        'and_more'      => 've üzeri',
    ],

    // ============================================
    // NAV — Navigasyon
    // ============================================
    'nav' => [
        'home'          => 'Ana Sayfa',
        'about'         => 'Hakkımızda',
        'products'      => 'Ürünler',
        'menu'          => 'Menü',
        'contact'       => 'İletişim',
        'story'         => 'Hikayemiz',
        'cart'          => 'Sepet',
        'orders'        => 'Siparişlerim',
        'login'         => 'Giriş Yap',
        'logout'        => 'Çıkış Yap',
        'register'      => 'Kayıt Ol',
        'dashboard'     => 'Kontrol Paneli',
        'settings'      => 'Ayarlar',
        'profile'       => 'Profil',
    ],

    // ============================================
    // BTN — Buton etiketleri
    // ============================================
    'btn' => [
        'add_to_cart'   => 'Sepete Ekle',
        'view_cart'     => 'Sepeti Gör',
        'checkout'      => 'Siparişi Tamamla',
        'order_now'     => 'Sipariş Ver',
        'contact_us'    => 'İletişime Geç',
        'discover'      => 'Keşfet',
        'explore'       => 'Keşfet',
        'view_menu'     => 'Menüyü Gör',
        'view_details'  => 'Detayları Gör',
        'back_to_menu'  => 'Menüye Dön',
        'remove'        => 'Kaldır',
        'clear_cart'    => 'Sepeti Boşalt',
        'try_again'     => 'Tekrar Dene',
        'go_back'       => 'Geri Dön',
    ],

    // ============================================
    // FORM — Form alanlari ve label'lari
    // ============================================
    'form' => [
        'name'          => 'Ad Soyad',
        'first_name'    => 'Ad',
        'last_name'     => 'Soyad',
        'email'         => 'E-posta',
        'phone'         => 'Telefon',
        'address'       => 'Adres',
        'city'          => 'Şehir',
        'district'      => 'İlçe',
        'postal_code'   => 'Posta Kodu',
        'password'      => 'Şifre',
        'password_confirm' => 'Şifre (Tekrar)',
        'message'       => 'Mesaj',
        'subject'       => 'Konu',
        'note_for_seller' => 'Satıcıya Not',
        'placeholder_name'    => 'Adınız Soyadınız',
        'placeholder_email'   => 'ornek@eposta.com',
        'placeholder_phone'   => '0555 555 55 55',
        'placeholder_address' => 'Teslimat adresi',
    ],

    // ============================================
    // ORDER — Siparis akisi
    // ============================================
    'order' => [
        'title'           => 'Siparişim',
        'your_cart'       => 'Sepetiniz',
        'cart_empty'      => 'Sepetiniz boş',
        'cart_empty_desc' => 'Menüden lezzetli ürünler ekleyebilirsiniz.',
        'cart_count'      => '{{count}} ürün',
        'select_portion'  => 'Porsiyon seçin',
        'out_of_stock'    => 'Tükendi',
        'limited_stock'   => 'Sınırlı',
        'in_stock'        => 'Stokta',
        'menu_empty'      => 'Henüz menüde ürün bulunmuyor.',
        'your_table'      => 'Masa {{no}}',
        'waiting'         => 'Beklemede',
        'approved'        => 'Onaylandı',
        'preparing'       => 'Hazırlanıyor',
        'ready'           => 'Hazır',
        'delivered'       => 'Teslim Edildi',
        'cancelled'       => 'İptal Edildi',
        'completed'       => 'Tamamlandı',
        'order_received'  => 'Siparişiniz alındı!',
        'order_number'    => 'Sipariş No: #{{id}}',
        'payment_method'  => 'Ödeme Yöntemi',
        'pay_cash'        => 'Nakit',
        'pay_card'        => 'Kredi Kartı',
        'pay_online'      => 'Online Ödeme',
        'delivery_type'   => 'Teslimat Türü',
        'delivery_home'   => 'Adrese Teslim',
        'pickup'          => 'Mağazadan Al',
    ],

    // ============================================
    // ADMIN — Yonetim paneli
    // ============================================
    'admin' => [
        'panel_title'     => 'Yönetim Paneli',
        'dashboard'       => 'Kontrol Paneli',
        'products'        => 'Ürünler',
        'categories'      => 'Kategoriler',
        'orders'          => 'Siparişler',
        'calendar'        => 'Takvim / Siparişler',
        'tables'          => 'Masalar',
        'table_orders'    => 'Masa Siparişleri',
        'kitchen'         => 'Mutfak Ekranı',
        'waiter'          => 'Garson Paneli',
        'reports'         => 'Satış Raporları',
        'customers'       => 'Kayıtlı Müşteriler',
        'messages'        => 'Mesajlar',
        'themes'          => 'Temalar',
        'activity_log'    => 'Aktivite Logları',
        'settings'        => 'Ayarlar',
        'general'         => 'Genel',
        'two_factor'      => 'İki Faktör (2FA)',
        'email_settings'  => 'Email (SMTP)',
        'sms_settings'    => 'SMS',
        'backup'          => 'Yedekleme',
        'view_site'       => 'Siteyi Görüntüle',
        'new_tab'         => 'Yeni sekme',
        'hello_user'      => 'Merhaba, {{name}}',
        'unread'          => '{{count}} okunmamış',
    ],

    // ============================================
    // ERROR — Hata mesajlari
    // ============================================
    'error' => [
        'generic'          => 'Bir hata oluştu. Lütfen tekrar deneyin.',
        'not_found'        => 'Sayfa bulunamadı.',
        'forbidden'        => 'Bu sayfaya erişim yetkiniz yok.',
        'unauthorized'     => 'Lütfen giriş yapın.',
        'csrf'             => 'Güvenlik doğrulama hatası.',
        'invalid_input'    => 'Geçersiz giriş.',
        'required_field'   => 'Bu alan zorunludur.',
        'invalid_email'    => 'Geçerli bir e-posta adresi giriniz.',
        'invalid_phone'    => 'Geçerli bir telefon numarası giriniz.',
        'password_mismatch'=> 'Şifreler eşleşmiyor.',
        'password_short'   => 'Şifre en az {{min}} karakter olmalıdır.',
        'qr_required'      => 'QR Kod Gerekli',
        'qr_required_desc' => 'Menüyü görüntülemek için lütfen masadaki QR kodu okutun.',
        'table_inactive'   => 'Masa Aktif Değil',
        'table_inactive_desc' => 'Bu masa şu an aktif değil. Lütfen garsondan yardım isteyin.',
        'out_of_stock_msg' => 'Bu ürün şu an tükenmiştir.',
        'rate_limit'       => 'Çok fazla istek. Lütfen biraz bekleyin.',
        'server_error'     => 'Sunucu hatası. Yakında tekrar deneyin.',
    ],

    // ============================================
    // SUCCESS — Basari mesajlari
    // ============================================
    'success' => [
        'saved'            => 'Kaydedildi.',
        'updated'          => 'Güncellendi.',
        'deleted'          => 'Silindi.',
        'created'          => 'Oluşturuldu.',
        'sent'             => 'Gönderildi.',
        'login'            => 'Giriş yapıldı.',
        'logout'           => 'Çıkış yapıldı.',
        'order_placed'     => 'Siparişiniz başarıyla alındı.',
        'added_to_cart'    => 'Ürün sepete eklendi.',
        'removed_from_cart'=> 'Ürün sepetten çıkarıldı.',
        'cart_cleared'     => 'Sepet boşaltıldı.',
        'message_sent'     => 'Mesajınız başarıyla iletildi.',
        'language_changed' => 'Dil değiştirildi.',
    ],

    // ============================================
    // HOME — Ana sayfa ozel metinleri
    // ============================================
    'home' => [
        'hero_title'       => 'Tatlı Düşler',
        'hero_subtitle'    => 'Butik Pasta & Tatlı',
        'about_title'      => 'Hikayemiz',
        'about_paragraph_1' => 'Pastalardan cheesecake\'lere, cupcake\'lerden el yapımı kurabiyelere kadar tüm tatlılarımızı sevgiyle ve tutkuyla hazırlıyoruz. Kaliteli malzemeler ve özenle seçilmiş tariflerle sizin için en özel lezzetleri yaratıyoruz.',
        'about_paragraph_2' => 'Sipariş üzerine üretim yapıyoruz; bu sayede her tatlımızın tazeliğini garanti ediyoruz. Doğum günlerinden düğünlere, kutlamalardan ikramlara, her özel anınızda yanınızdayız.',
        'promo_student'    => 'Üniversite öğrencilerine indirim!',
        'contact_title'    => 'İletişim',
        'contact_subtitle' => 'Bize ulaşın, size en özel tatlıyı hazırlayalım.',
        'footer_copyright' => '© {{year}} Tatlı Düşler. Tüm hakları saklıdır.',
        'products_title'   => 'Lezzetlerimiz',
        'products_subtitle'=> 'El yapımı, taze ve her biri özenle hazırlanmış ürünlerimiz',
        'delivery_title'   => 'Teslimat Bilgisi',
        'calendar_title'   => 'Müsaitlik Takvimi',
        'calendar_subtitle'=> 'Sipariş vermeden önce uygunluk durumumuzu kontrol edin',
        'faq_title'        => 'Sıkça Sorulan Sorular',
        'faq_subtitle'     => 'Merak ettiğiniz soruların cevapları',
        'footer_tagline'   => 'El yapımı lezzetler',
    ],

    // ============================================
    // LANGUAGE — Dil secici
    // ============================================
    'language' => [
        'switcher_label'   => 'Dil',
        'turkish'          => 'Türkçe',
        'english'          => 'İngilizce',
        'tr_short'         => 'TR',
        'en_short'         => 'EN',
    ],

    // ============================================
    // A11Y — Erisilebilirlik
    // ============================================
    'a11y' => [
        'skip_to_content'  => 'İçeriğe atla',
        'toggle_menu'      => 'Menüyü aç/kapat',
        'toggle_theme'     => 'Tema değiştir',
        'switch_to_dark'   => 'Koyu temaya geç',
        'switch_to_light'  => 'Açık temaya geç',
        'main_menu'        => 'Ana menü',
        'language_menu'    => 'Dil seçimi',
    ],
];
