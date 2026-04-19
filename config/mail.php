<?php
/**
 * Mail Konfigurasyonu
 *
 * SMTP ayarlari ve email gonderim konfigurasyonu.
 * .env dosyasindan ayarlari okur.
 *
 * @package Pastane
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Mail Driver
    |--------------------------------------------------------------------------
    | Desteklenen: smtp, mail (native PHP mail()), log
    | - smtp: SMTP sunucusu uzerinden gonderir (PHPMailer veya fsockopen ile)
    | - mail: PHP'nin yerlesik mail() fonksiyonunu kullanir
    | - log: Email'leri storage/logs/email.log'a yazar (gercek gonderim yapmaz)
    */
    'driver' => env('MAIL_DRIVER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | SMTP Ayarlari
    |--------------------------------------------------------------------------
    */
    'host'       => env('MAIL_HOST', 'smtp.mailtrap.io'),
    'port'       => (int) env('MAIL_PORT', 2525),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    /*
    |--------------------------------------------------------------------------
    | Gonderici Bilgileri
    |--------------------------------------------------------------------------
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'Tatlı Düşler')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reply-To (opsiyonel)
    |--------------------------------------------------------------------------
    */
    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS', ''),
        'name'    => env('MAIL_REPLY_TO_NAME', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Ayarlari
    |--------------------------------------------------------------------------
    */
    'templates_path' => dirname(__DIR__) . '/storage/views/emails',

    /*
    |--------------------------------------------------------------------------
    | Log Ayarlari
    |--------------------------------------------------------------------------
    | Her gonderim denemesi bu dosyaya yazilir.
    */
    'log_path' => dirname(__DIR__) . '/storage/logs/email.log',

    /*
    |--------------------------------------------------------------------------
    | Timeout (saniye)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('MAIL_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Enabled — false ise gonderim yapilmaz (log'a yazar)
    |--------------------------------------------------------------------------
    */
    'enabled' => env('MAIL_ENABLED', true),
];
