<?php
/**
 * EmailService
 *
 * Email gonderim servisi. PHPMailer yoksa native mail() veya fsockopen
 * tabanli kendi SMTP istemcimize duser.
 *
 * Kullanim:
 *   $email = new EmailService();
 *   $email->send(
 *       'musteri@example.com',
 *       'Siparis Onayi',
 *       'siparis-onay',
 *       ['musteri_adi' => 'Ali Veli', 'siparis_no' => 123]
 *   );
 *
 * @package Pastane
 * @since 1.0.0
 */

declare(strict_types=1);

class EmailService
{
    /**
     * @var array Mail konfigurasyonu
     */
    private array $config;

    /**
     * @var string Son hata mesaji
     */
    private string $lastError = '';

    /**
     * Constructor
     *
     * @param array|null $config Opsiyonel konfigurasyon (test icin)
     */
    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = array_merge($this->defaultConfig(), $config);
            return;
        }

        $configPath = dirname(__DIR__) . '/config/mail.php';
        if (file_exists($configPath)) {
            $this->config = array_merge($this->defaultConfig(), require $configPath);
        } else {
            $this->config = $this->defaultConfig();
        }
    }

    /**
     * Varsayilan konfigurasyon
     *
     * @return array
     */
    private function defaultConfig(): array
    {
        return [
            'driver'         => 'log',
            'host'           => 'localhost',
            'port'           => 25,
            'username'       => '',
            'password'       => '',
            'encryption'     => '',
            'from'           => ['address' => 'noreply@example.com', 'name' => 'Pastane'],
            'reply_to'       => ['address' => '', 'name' => ''],
            'templates_path' => dirname(__DIR__) . '/storage/views/emails',
            'log_path'       => dirname(__DIR__) . '/storage/logs/email.log',
            'timeout'        => 10,
            'enabled'        => true,
        ];
    }

    /**
     * Email gonder
     *
     * @param string $to Alici email adresi
     * @param string $subject Konu
     * @param string $template Template adi (dosya uzantisiz, ornegin: siparis-onay)
     * @param array $data Template degiskenleri
     * @param array $options Ek secenekler [cc, bcc, attachments]
     * @return bool Basarili olup olmadigi
     */
    public function send(string $to, string $subject, string $template, array $data = [], array $options = []): bool
    {
        // Email adresi dogrulama
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Gecersiz alici email adresi: ' . $to;
            $this->log('gonderim_hatasi', [
                'alici'  => $to,
                'konu'   => $subject,
                'hata'   => $this->lastError,
            ]);
            return false;
        }

        // Template render
        try {
            $body = $this->renderTemplate($template, $data);
        } catch (\Throwable $e) {
            $this->lastError = 'Template render hatasi: ' . $e->getMessage();
            $this->log('template_hatasi', [
                'alici'    => $to,
                'template' => $template,
                'hata'     => $e->getMessage(),
            ]);
            return false;
        }

        // Gonderim kapali mi?
        if (empty($this->config['enabled'])) {
            $this->log('gonderim_devre_disi', [
                'alici' => $to,
                'konu'  => $subject,
            ]);
            return true; // Sessizce basarili say
        }

        // Driver'a gore gonder
        $driver = $this->config['driver'] ?? 'log';

        try {
            $result = match ($driver) {
                'log'  => $this->sendViaLog($to, $subject, $body),
                'mail' => $this->sendViaMail($to, $subject, $body, $options),
                'smtp' => $this->sendViaSmtp($to, $subject, $body, $options),
                default => throw new \RuntimeException('Desteklenmeyen mail driver: ' . $driver),
            };
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->log('gonderim_hatasi', [
                'alici'  => $to,
                'konu'   => $subject,
                'driver' => $driver,
                'hata'   => $e->getMessage(),
            ]);
            return false;
        }

        $this->log($result ? 'gonderim_basarili' : 'gonderim_basarisiz', [
            'alici'  => $to,
            'konu'   => $subject,
            'driver' => $driver,
        ]);

        return $result;
    }

    /**
     * Template render
     *
     * Basit {{key}} replace ile HTML template'i degisken degerleriyle doldurur.
     *
     * @param string $template Template dosya adi (uzantisiz)
     * @param array $data Degiskenler
     * @return string Render edilmis HTML
     * @throws \RuntimeException Template bulunamazsa
     */
    public function renderTemplate(string $template, array $data): string
    {
        $templatesPath = rtrim($this->config['templates_path'] ?? '', DIRECTORY_SEPARATOR);
        $templateFile = $templatesPath . DIRECTORY_SEPARATOR . $template . '.html';

        if (!file_exists($templateFile)) {
            throw new \RuntimeException('Email template bulunamadi: ' . $templateFile);
        }

        $content = file_get_contents($templateFile);
        if ($content === false) {
            throw new \RuntimeException('Email template okunamadi: ' . $templateFile);
        }

        // Önce raw {{{key}}} replace (escape'siz), sonra escape'li {{key}}
        // Sıra ÖNEMLİ: {{{ önce match edilmeli, yoksa {{key}} {}'ye döner
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            // Raw versiyon: {{{key}}} — HTML escape YAPMA
            $rawPlaceholder = '{{{' . $key . '}}}';
            $content = str_replace($rawPlaceholder, (string) $value, $content);
        }
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            // Escape'li versiyon: {{key}}
            $placeholder = '{{' . $key . '}}';
            $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = str_replace($placeholder, $escaped, $content);
        }

        return $content;
    }

    /**
     * Test email gonder
     *
     * Admin UI icin basit test fonksiyonu.
     *
     * @param string $to Alici
     * @return bool
     */
    public function sendTestEmail(string $to): bool
    {
        $data = [
            'musteri_adi'  => 'Test Kullanici',
            'siparis_no'   => 'TEST-' . date('YmdHis'),
            'siparis_tarihi' => date('d.m.Y H:i'),
            'toplam_tutar' => '0,00 ₺',
            'urun_listesi' => '<tr><td>Test Urunu</td><td>1</td><td>0,00 ₺</td></tr>',
            'adres'        => 'Test Adres',
            'telefon'      => '0555 555 55 55',
            'site_adi'     => $this->config['from']['name'] ?? 'Pastane',
            'yil'          => date('Y'),
        ];

        return $this->send($to, '[TEST] Email Sistemi Calisir Durumda', 'siparis-onay', $data);
    }

    /**
     * Log driver: email'i log dosyasina yaz
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    private function sendViaLog(string $to, string $subject, string $body): bool
    {
        $this->log('log_driver_gonderim', [
            'alici'   => $to,
            'konu'    => $subject,
            'govde_boyut' => strlen($body),
        ]);
        return true;
    }

    /**
     * Native mail() ile gonder
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $options
     * @return bool
     */
    private function sendViaMail(string $to, string $subject, string $body, array $options = []): bool
    {
        $fromAddr = $this->config['from']['address'] ?? 'noreply@example.com';
        $fromName = $this->config['from']['name'] ?? '';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . ($fromName !== '' ? sprintf('"%s" <%s>', $fromName, $fromAddr) : $fromAddr),
        ];

        if (!empty($this->config['reply_to']['address'])) {
            $headers[] = 'Reply-To: ' . $this->config['reply_to']['address'];
        }

        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . $options['cc'];
        }
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        // @suppress: mail() E_WARNING cikariyor, biz false donmesini kontrol ediyoruz
        return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }

    /**
     * SMTP ile gonder (PHPMailer varsa onu kullan, yoksa fsockopen)
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $options
     * @return bool
     * @throws \RuntimeException SMTP baglanti hatasi
     */
    private function sendViaSmtp(string $to, string $subject, string $body, array $options = []): bool
    {
        // PHPMailer varsa kullan
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendViaPhpMailer($to, $subject, $body, $options);
        }

        // Fallback: kendi basit SMTP istemcimiz
        return $this->sendViaFsockSmtp($to, $subject, $body);
    }

    /**
     * PHPMailer ile SMTP gonderim
     *
     * @codeCoverageIgnore
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $options
     * @return bool
     */
    private function sendViaPhpMailer(string $to, string $subject, string $body, array $options = []): bool
    {
        $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
        /** @var object $mail */
        $mail = new $mailerClass(true);

        $mail->isSMTP();
        $mail->Host       = $this->config['host'];
        $mail->Port       = $this->config['port'];
        $mail->SMTPAuth   = !empty($this->config['username']);
        $mail->Username   = $this->config['username'];
        $mail->Password   = $this->config['password'];
        $mail->SMTPSecure = $this->config['encryption'] ?? '';
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = $this->config['timeout'] ?? 10;

        $mail->setFrom($this->config['from']['address'], $this->config['from']['name'] ?? '');
        $mail->addAddress($to);

        if (!empty($this->config['reply_to']['address'])) {
            $mail->addReplyTo($this->config['reply_to']['address'], $this->config['reply_to']['name'] ?? '');
        }

        if (!empty($options['cc'])) {
            $mail->addCC($options['cc']);
        }
        if (!empty($options['bcc'])) {
            $mail->addBCC($options['bcc']);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        return $mail->send();
    }

    /**
     * fsockopen ile basit SMTP gonderim (PHPMailer yoksa fallback)
     *
     * Sadece temel PLAIN auth destekler. TLS/SSL destegi icin stream_context
     * ve tls_crypto kullanilir.
     *
     * @codeCoverageIgnore
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     * @throws \RuntimeException
     */
    private function sendViaFsockSmtp(string $to, string $subject, string $body): bool
    {
        $host = $this->config['host'];
        $port = (int) $this->config['port'];
        $encryption = strtolower($this->config['encryption'] ?? '');
        $timeout = (int) ($this->config['timeout'] ?? 10);

        // SSL icin ssl:// prefix
        $connectHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;

        $socket = @fsockopen($connectHost, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new \RuntimeException(sprintf('SMTP baglanti hatasi: %s (%d)', $errstr, $errno));
        }

        $read = function () use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };

        $write = function (string $cmd) use ($socket): void {
            fwrite($socket, $cmd . "\r\n");
        };

        $read(); // Greeting
        $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $read();

        if ($encryption === 'tls') {
            $write('STARTTLS');
            $read();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $read();
        }

        // AUTH LOGIN
        if (!empty($this->config['username'])) {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($this->config['username']));
            $read();
            $write(base64_encode($this->config['password']));
            $authResponse = $read();
            if (!str_starts_with(trim($authResponse), '235')) {
                fclose($socket);
                throw new \RuntimeException('SMTP auth hatasi: ' . trim($authResponse));
            }
        }

        $fromAddr = $this->config['from']['address'];
        $write('MAIL FROM:<' . $fromAddr . '>');
        $read();
        $write('RCPT TO:<' . $to . '>');
        $read();
        $write('DATA');
        $read();

        $fromName = $this->config['from']['name'] ?? '';
        $fromHeader = $fromName !== '' ? sprintf('"%s" <%s>', $fromName, $fromAddr) : $fromAddr;

        $data = "From: {$fromHeader}\r\n";
        $data .= "To: {$to}\r\n";
        $data .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $data .= "MIME-Version: 1.0\r\n";
        $data .= "Content-Type: text/html; charset=UTF-8\r\n";
        $data .= "\r\n";
        $data .= $body;
        $data .= "\r\n.";
        $write($data);
        $response = $read();

        $write('QUIT');
        fclose($socket);

        return str_starts_with(trim($response), '250');
    }

    /**
     * Son hata mesajini dondur
     *
     * @return string
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Log kaydi yaz
     *
     * @param string $event
     * @param array $context
     * @return void
     */
    private function log(string $event, array $context = []): void
    {
        $logPath = $this->config['log_path'] ?? null;
        if (!$logPath) {
            return;
        }

        try {
            $dir = dirname($logPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $line = sprintf(
                "[%s] [%s] %s\n",
                date('Y-m-d H:i:s'),
                $event,
                json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Loglama hatasi servisi bloklamasin
        }
    }
}
