<?php

declare(strict_types=1);

namespace Pastane\Validators;

use Pastane\Exceptions\ValidationException;

/**
 * Masa Siparis Dogrulayici
 *
 * QR Menu sistemi siparis verilerini dogrular.
 * Durum guncelleme, siparis olusturma ve kalem validasyonlari.
 *
 * @package Pastane\Validators
 * @since 1.0.0
 */
class MasaSiparisValidator extends BaseValidator
{
    /**
     * Gecerli siparis durumlari (ENUM)
     */
    public const GECERLI_DURUMLAR = [
        'beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal',
    ];

    /**
     * Gecerli porsiyon degerleri
     */
    public const GECERLI_PORSIYONLAR = ['4kisi', '6kisi', '8kisi', '10kisi'];

    /**
     * @var string Validasyon senaryosu
     */
    private string $scenario;

    /**
     * Constructor
     *
     * @param string $scenario Senaryo: 'durum_guncelle', 'siparis_olustur'
     */
    public function __construct(string $scenario = 'siparis_olustur')
    {
        $this->scenario = $scenario;
    }

    /**
     * Senaryoya gore dogrulama kurallarini dondur
     *
     * @return array
     */
    protected function rules(): array
    {
        return match ($this->scenario) {
            'durum_guncelle' => [
                'yeni_durum' => ['required', 'string', 'in:' . implode(',', self::GECERLI_DURUMLAR)],
            ],
            'siparis_olustur' => [
                'oturum_token' => ['required', 'string'],
                'kalemler'     => ['required'],
                'siparis_notu' => ['nullable', 'string', 'max:500'],
            ],
            default => [],
        };
    }

    /**
     * Turkce hata mesajlari
     *
     * @return array
     */
    protected function messages(): array
    {
        return [
            'yeni_durum.required' => 'Yeni durum alani zorunludur.',
            'yeni_durum.string'   => 'Durum alani metin olmalidir.',
            'yeni_durum.in'       => 'Gecersiz durum degeri. Gecerli degerler: ' . implode(', ', self::GECERLI_DURUMLAR),
            'oturum_token.required' => 'Oturum token zorunludur.',
            'oturum_token.string'   => 'Oturum token metin olmalidir.',
            'kalemler.required'     => 'En az bir siparis kalemi zorunludur.',
            'siparis_notu.string'   => 'Siparis notu metin olmalidir.',
            'siparis_notu.max'      => 'Siparis notu en fazla 500 karakter olabilir.',
        ];
    }

    /**
     * Veriyi dogrula — ek is kurallariyla
     *
     * @param array $data
     * @return array Temizlenmis veri
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $cleaned = parent::validate($data);

        if ($this->scenario === 'siparis_olustur') {
            $this->validateKalemler($data['kalemler'] ?? []);

            // XSS temizligi: siparis notu
            if (!empty($cleaned['siparis_notu'])) {
                $cleaned['siparis_notu'] = htmlspecialchars(
                    $cleaned['siparis_notu'],
                    ENT_QUOTES,
                    'UTF-8'
                );
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Dogrulama hatasi.', $this->errors);
        }

        return $cleaned;
    }

    /**
     * Siparis kalemlerini dogrula
     *
     * Her kalem icin: urun_id (zorunlu, pozitif int), adet (zorunlu, 1-99),
     * porsiyon (opsiyonel, gecerli deger), ozel_not (opsiyonel, max 200).
     *
     * @param mixed $kalemler
     * @return void
     */
    private function validateKalemler(mixed $kalemler): void
    {
        if (!is_array($kalemler) || empty($kalemler)) {
            $this->errors['kalemler'][] = 'En az bir urun secmelisiniz.';
            return;
        }

        if (count($kalemler) > 50) {
            $this->errors['kalemler'][] = 'Tek sipariste en fazla 50 kalem olabilir.';
            return;
        }

        foreach ($kalemler as $index => $kalem) {
            if (!is_array($kalem)) {
                $this->errors['kalemler'][] = "Gecersiz kalem formati (index: {$index}).";
                continue;
            }

            // urun_id: zorunlu, pozitif int
            $urunId = isset($kalem['urun_id']) ? (int)$kalem['urun_id'] : 0;
            if ($urunId < 1) {
                $this->errors['kalemler'][] = "Gecersiz urun ID (index: {$index}).";
            }

            // adet: zorunlu, 1-99
            $adet = isset($kalem['adet']) ? (int)$kalem['adet'] : 0;
            if ($adet < 1 || $adet > 99) {
                $this->errors['kalemler'][] = "Adet 1 ile 99 arasinda olmalidir (index: {$index}).";
            }

            // porsiyon: opsiyonel, gecerli deger
            if (isset($kalem['porsiyon']) && $kalem['porsiyon'] !== '' && $kalem['porsiyon'] !== null) {
                $porsiyon = trim((string)$kalem['porsiyon']);
                if (!in_array($porsiyon, self::GECERLI_PORSIYONLAR, true)) {
                    $this->errors['kalemler'][] = "Gecersiz porsiyon degeri (index: {$index}). Gecerli: "
                        . implode(', ', self::GECERLI_PORSIYONLAR);
                }
            }

            // ozel_not: opsiyonel, max 200 karakter
            if (isset($kalem['ozel_not']) && $kalem['ozel_not'] !== null) {
                if (mb_strlen((string)$kalem['ozel_not']) > 200) {
                    $this->errors['kalemler'][] = "Ozel not en fazla 200 karakter olabilir (index: {$index}).";
                }
            }
        }
    }
}
