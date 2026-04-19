<?php
/**
 * SmsService
 *
 * SMS gönderim servisi. Driver-based (log, netgsm, twilio).
 * Türk telefon formatı normalize + template engine (placeholder: {{key}}).
 *
 * Kullanım:
 *   $sms = new SmsService();
 *   $sms->send('05551234567', 'Siparisiniz alindi #123');
 *   $sms->sendTemplate('05551234567', 'siparis-onay', [
 *       'siparis_no' => 123,
 *       'musteri_adi' => 'Ali',
 *   ]);
 *
 * @package Pastane
 * @since 2.0.0-sprint2
 */

declare(strict_types=1);

class SmsService
{
    /**
     * @var array Driver-agnostik konfigürasyon
     */
    private array $config;

    /**
     * @var string Son hata mesajı
     */
    private string $lastError = '';

    /**
     * @var callable|null HTTP client override (test için)
     */
    private $httpClient = null;

    /**
     * Constructor
     *
     * @param array|null    $config      Konfigürasyon override (test için)
     * @param callable|null $httpClient  HTTP client override: fn(string $method, string $url, array $opts): array{status:int, body:string}
     */
    public function __construct(?array $config = null, ?callable $httpClient = null)
    {
        if ($config !== null) {
            $this->config = array_merge($this->defaultConfig(), $config);
        } else {
            $configPath = dirname(__DIR__) . '/config/sms.php';
            if (file_exists($configPath)) {
                $this->config = array_merge($this->defaultConfig(), require $configPath);
            } else {
                $this->config = $this->defaultConfig();
            }
        }

        $this->httpClient = $httpClient;
    }

    /**
     * Varsayılan konfigürasyon
     *
     * @return array
     */
    private function defaultConfig(): array
    {
        return [
            'driver'         => 'log',
            'api_key'        => '',
            'api_secret'     => '',
            'sender'         => 'PASTANE',
            'templates_path' => dirname(__DIR__) . '/storage/views/sms',
            'log_path'       => dirname(__DIR__) . '/storage/logs/sms.log',
            'timeout'        => 10,
            'enabled'        => true,
            'country_code'   => '90',
            'netgsm'         => ['endpoint' => 'https://api.netgsm.com.tr/sms/send/get'],
            'twilio'         => ['endpoint_template' => 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json'],
        ];
    }

    /**
     * Template ile SMS gönder
     *
     * @param string $phone
     * @param string $template Template adı (uzantısız, ör: 'siparis-onay')
     * @param array  $vars     Placeholder değerleri
     * @return bool
     */
    public function sendTemplate(string $phone, string $template, array $vars = []): bool
    {
        try {
            $body = $this->renderTemplate($template, $vars);
        } catch (\Throwable $e) {
            $this->lastError = 'Template render hatası: ' . $e->getMessage();
            $this->log('template_hatasi', [
                'telefon'  => $phone,
                'template' => $template,
                'hata'     => $e->getMessage(),
            ]);
            return false;
        }

        return $this->send($phone, $body);
    }

    /**
     * SMS gönder
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public function send(string $phone, string $message): bool
    {
        // Mesaj boş olmasın
        $message = trim($message);
        if ($message === '') {
            $this->lastError = 'SMS mesajı boş olamaz.';
            return false;
        }

        // Telefon normalize
        $normalized = $this->normalizePhone($phone);
        if ($normalized === null) {
            $this->lastError = 'Geçersiz telefon numarası: ' . $phone;
            $this->log('gecersiz_telefon', [
                'telefon_raw' => $phone,
            ]);
            return false;
        }

        // Gönderim kapalı mı?
        if (empty($this->config['enabled'])) {
            $this->log('gonderim_devre_disi', [
                'telefon' => $normalized,
                'mesaj'   => mb_substr($message, 0, 60) . (mb_strlen($message) > 60 ? '...' : ''),
            ]);
            return true; // Sessizce başarı say
        }

        $driver = (string) ($this->config['driver'] ?? 'log');

        try {
            $result = match ($driver) {
                'log'    => $this->sendViaLog($normalized, $message),
                'netgsm' => $this->sendViaNetgsm($normalized, $message),
                'twilio' => $this->sendViaTwilio($normalized, $message),
                default  => throw new \RuntimeException('Desteklenmeyen SMS driver: ' . $driver),
            };
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->log('gonderim_hatasi', [
                'telefon' => $normalized,
                'driver'  => $driver,
                'hata'    => $e->getMessage(),
            ]);
            return false;
        }

        $this->log($result ? 'gonderim_basarili' : 'gonderim_basarisiz', [
            'telefon' => $normalized,
            'driver'  => $driver,
            'boyut'   => mb_strlen($message),
        ]);

        return $result;
    }

    /**
     * Türk telefon formatı normalize — E.164'e uygun (+90XXXXXXXXXX)
     *
     * Kabul edilen formatlar:
     *   05551234567, 5551234567, 905551234567, +905551234567,
     *   +90 (555) 123 45 67, 0 555 123 45 67
     *
     * @param string $raw
     * @return string|null 10 haneli numara başında ülke koduyla: 905551234567, geçersizse null
     */
    public function normalizePhone(string $raw): ?string
    {
        // Sadece rakamları al
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $cc = (string) ($this->config['country_code'] ?? '90');

        if ($digits === '') {
            return null;
        }

        // 0 ile başlıyorsa kaldır (ör. 05551234567 → 5551234567)
        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        // Zaten ülke kodu varsa (905xx...) olduğu gibi bırak
        if (str_starts_with($digits, $cc) && strlen($digits) === strlen($cc) + 10) {
            return $digits;
        }

        // Yoksa ülke kodu ekle (5xx... → 905xx...)
        if (strlen($digits) === 10) {
            return $cc . $digits;
        }

        // Geçersiz uzunluk
        return null;
    }

    /**
     * Template render ({{key}} placeholder'ları)
     *
     * @param string $template
     * @param array  $vars
     * @return string
     * @throws \RuntimeException Template bulunamazsa
     */
    public function renderTemplate(string $template, array $vars): string
    {
        // Path traversal koruması
        if (!preg_match('/^[a-z0-9_\-]+$/i', $template)) {
            throw new \InvalidArgumentException('Geçersiz template adı: ' . $template);
        }

        $path = rtrim((string) ($this->config['templates_path'] ?? ''), DIRECTORY_SEPARATOR);
        $file = $path . DIRECTORY_SEPARATOR . $template . '.txt';

        if (!file_exists($file)) {
            throw new \RuntimeException('SMS template bulunamadı: ' . $file);
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException('SMS template okunamadı: ' . $file);
        }

        foreach ($vars as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        return trim($content);
    }

    /**
     * Log driver (sadece log yazar)
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    private function sendViaLog(string $phone, string $message): bool
    {
        $this->log('log_driver_gonderim', [
            'telefon' => $phone,
            'mesaj'   => $message,
        ]);
        return true;
    }

    /**
     * NetGSM HTTP API ile gönder
     *
     * API: GET https://api.netgsm.com.tr/sms/send/get?usercode=XX&password=XX&gsmno=XX&message=XX&msgheader=XX
     * Başarı: response body "00" ile başlar
     *
     * @param string $phone
     * @param string $message
     * @return bool
     * @throws \RuntimeException
     */
    private function sendViaNetgsm(string $phone, string $message): bool
    {
        $endpoint = (string) ($this->config['netgsm']['endpoint'] ?? '');
        $user = (string) ($this->config['api_key'] ?? '');
        $pass = (string) ($this->config['api_secret'] ?? '');
        $sender = (string) ($this->config['sender'] ?? '');

        if ($user === '' || $pass === '' || $endpoint === '') {
            throw new \RuntimeException('NetGSM konfigürasyonu eksik (SMS_API_KEY/SMS_API_SECRET).');
        }

        $query = http_build_query([
            'usercode'  => $user,
            'password'  => $pass,
            'gsmno'     => $phone,
            'message'   => $message,
            'msgheader' => $sender,
        ]);

        $url = $endpoint . '?' . $query;
        $response = $this->httpGet($url);

        $body = trim($response['body'] ?? '');
        // NetGSM: "00" veya "00 <bulk_id>" = başarı
        return $response['status'] === 200 && str_starts_with($body, '00');
    }

    /**
     * Twilio REST API ile gönder
     *
     * POST https://api.twilio.com/2010-04-01/Accounts/{SID}/Messages.json
     * Basic Auth: {SID}:{AUTH_TOKEN}
     * Body: From=+{sender}&To=+{phone}&Body={message}
     *
     * @param string $phone
     * @param string $message
     * @return bool
     * @throws \RuntimeException
     */
    private function sendViaTwilio(string $phone, string $message): bool
    {
        $sid = (string) ($this->config['api_key'] ?? '');
        $token = (string) ($this->config['api_secret'] ?? '');
        $sender = (string) ($this->config['sender'] ?? '');

        if ($sid === '' || $token === '' || $sender === '') {
            throw new \RuntimeException('Twilio konfigürasyonu eksik (SMS_API_KEY/SMS_API_SECRET/SMS_SENDER).');
        }

        $endpoint = sprintf((string) ($this->config['twilio']['endpoint_template'] ?? ''), $sid);

        $response = $this->httpPost($endpoint, [
            'From' => $sender,
            'To'   => '+' . $phone,
            'Body' => $message,
        ], [
            'Authorization: Basic ' . base64_encode($sid . ':' . $token),
        ]);

        // Twilio success: 201 Created
        return $response['status'] === 201;
    }

    /**
     * HTTP GET — cURL veya fallback (test için httpClient override edilebilir)
     *
     * @param string $url
     * @return array{status:int, body:string}
     */
    private function httpGet(string $url): array
    {
        if ($this->httpClient !== null) {
            return ($this->httpClient)('GET', $url, []);
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL extension yüklü değil.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) ($this->config['timeout'] ?? 10),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new \RuntimeException('HTTP GET hatası: ' . $err);
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * HTTP POST (form-encoded)
     *
     * @param string   $url
     * @param array    $data
     * @param string[] $headers
     * @return array{status:int, body:string}
     */
    private function httpPost(string $url, array $data, array $headers = []): array
    {
        if ($this->httpClient !== null) {
            return ($this->httpClient)('POST', $url, ['data' => $data, 'headers' => $headers]);
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL extension yüklü değil.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) ($this->config['timeout'] ?? 10),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new \RuntimeException('HTTP POST hatası: ' . $err);
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * Log yaz
     *
     * @param string $event
     * @param array  $context
     * @return void
     */
    private function log(string $event, array $context): void
    {
        $path = (string) ($this->config['log_path'] ?? '');
        if ($path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = sprintf(
            "[%s] %s %s\n",
            date('c'),
            $event,
            (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Son hata mesajı
     *
     * @return string
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Konfigürasyonu döndür (admin UI için)
     *
     * @return array Sifreler gizlenmiş hali
     */
    public function getConfig(): array
    {
        $c = $this->config;
        if (!empty($c['api_secret'])) {
            $c['api_secret'] = '***gizli***';
        }
        return $c;
    }

    /**
     * Test SMS gönder
     *
     * @param string $phone
     * @return bool
     */
    public function sendTestSms(string $phone): bool
    {
        $message = '[TEST] Pastane SMS sistemi calisiyor. Zaman: ' . date('d.m.Y H:i');
        return $this->send($phone, $message);
    }
}
