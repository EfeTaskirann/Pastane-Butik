<?php
/**
 * SMS Konfigürasyonu
 *
 * Driver'lar:
 *   - log:    SMS'leri sadece storage/logs/sms.log'a yazar (prod'a çıkmadan önce test için)
 *   - netgsm: NetGSM HTTP API (Türkiye SMS gateway)
 *   - twilio: Twilio REST API
 *
 * .env değişkenleri:
 *   SMS_DRIVER      = log | netgsm | twilio
 *   SMS_API_KEY     = driver-specific (NetGSM: kullanıcı adı, Twilio: Account SID)
 *   SMS_API_SECRET  = driver-specific (NetGSM: şifre, Twilio: Auth Token)
 *   SMS_SENDER      = gönderici adı/numarası (NetGSM: onaylı başlık, Twilio: +E.164 telefon)
 *   SMS_ENABLED     = 1/0 (kapalıyken log'a yazar, gerçek gönderim yok)
 *
 * @package Pastane
 * @since 2.0.0-sprint2
 */

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Kimlik Bilgileri
    |--------------------------------------------------------------------------
    */
    'api_key'    => env('SMS_API_KEY', ''),
    'api_secret' => env('SMS_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Gönderici
    |--------------------------------------------------------------------------
    */
    'sender' => env('SMS_SENDER', 'PASTANE'),

    /*
    |--------------------------------------------------------------------------
    | Template Yolu
    |--------------------------------------------------------------------------
    | SMS template'leri .txt dosyaları olarak saklanır.
    | Placeholder formatı: {{degisken_adi}}
    */
    'templates_path' => dirname(__DIR__) . '/storage/views/sms',

    /*
    |--------------------------------------------------------------------------
    | Log Dosyası
    |--------------------------------------------------------------------------
    */
    'log_path' => dirname(__DIR__) . '/storage/logs/sms.log',

    /*
    |--------------------------------------------------------------------------
    | Timeout (saniye)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SMS_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Enabled — false ise gerçek gönderim yok (sadece log)
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('SMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Ülke Kodu (telefon normalize — Türkiye varsayılan)
    |--------------------------------------------------------------------------
    */
    'country_code' => env('SMS_COUNTRY_CODE', '90'),

    /*
    |--------------------------------------------------------------------------
    | NetGSM Özel
    |--------------------------------------------------------------------------
    */
    'netgsm' => [
        'endpoint' => env('NETGSM_ENDPOINT', 'https://api.netgsm.com.tr/sms/send/get'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio Özel
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'endpoint_template' => 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
    ],
];
