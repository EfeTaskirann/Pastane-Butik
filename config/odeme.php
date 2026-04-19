<?php
/**
 * Odeme Gateway Konfigurasyonu
 *
 * Desteklenen gateway'ler: test, iyzico, paytr
 * TEST modu gelistirme ortami icin varsayilan gateway'dir.
 *
 * @package Pastane
 * @since 1.0.0
 */

// Gateway degerini dogrula, gecersizse 'test'e fallback yap
$desteklenenGatewayler = ['test', 'iyzico'];
$istenenGateway = env('ODEME_GATEWAY', 'test');

if (!in_array($istenenGateway, $desteklenenGatewayler, true)) {
    error_log(sprintf(
        '[Odeme Config] Gecersiz gateway degeri: "%s". Desteklenen: %s. Varsayilan "test" kullanilacak.',
        $istenenGateway,
        implode(', ', $desteklenenGatewayler)
    ));
    $istenenGateway = 'test';
}

return [
    /*
    |--------------------------------------------------------------------------
    | Aktif Odeme Gateway
    |--------------------------------------------------------------------------
    | 'test'   - Gercek API cagrisi yapmadan calisan test modu (varsayilan)
    | 'iyzico' - iyzico odeme entegrasyonu (cURL ile)
    | 'paytr'  - PayTR entegrasyonu (gelecekte)
    */
    'gateway' => $istenenGateway,

    /*
    |--------------------------------------------------------------------------
    | iyzico Ayarlari
    |--------------------------------------------------------------------------
    */
    'iyzico' => [
        'api_key'    => env('IYZICO_API_KEY', ''),
        'secret_key' => env('IYZICO_SECRET_KEY', ''),
        'base_url'   => env('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Gateway Ayarlari
    |--------------------------------------------------------------------------
    | Gelistirme ortaminda gercek API cagrisi yapmadan odeme simulasyonu.
    */
    'test' => [
        'otomatik_onay'  => true,   // Test modunda odeme otomatik basarili
        'gecikme_saniye'  => 0,      // Simulasyon gecikmesi (saniye)
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback URL'leri
    |--------------------------------------------------------------------------
    */
    'callback_url' => env('APP_URL', 'http://localhost') . '/menu/odeme-callback.php',
    'basari_url'   => env('APP_URL', 'http://localhost') . '/menu/odeme-sonuc.php',

    /*
    |--------------------------------------------------------------------------
    | Para Birimi
    |--------------------------------------------------------------------------
    */
    'para_birimi' => 'TRY',
];
