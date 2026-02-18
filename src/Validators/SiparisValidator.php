<?php

declare(strict_types=1);

namespace Pastane\Validators;

/**
 * Sipariş Doğrulayıcı
 *
 * Sipariş oluşturma/güncelleme verilerini doğrular.
 *
 * @package Pastane\Validators
 * @since 1.0.0
 */
class SiparisValidator extends BaseValidator
{
    private string $scenario;

    public function __construct(string $scenario = 'create')
    {
        $this->scenario = $scenario;
    }

    protected function rules(): array
    {
        if ($this->scenario === 'status') {
            return [
                'durum' => ['required', 'in:beklemede,onaylandi,hazirlaniyor,teslim_edildi,iptal'],
            ];
        }

        return [
            'ad_soyad' => ['required', 'string', 'min:2', 'max:100'],
            'telefon' => ['required', 'phone'],
            'email' => ['nullable', 'email'],
            'tarih' => ['required', 'date'],
            'saat' => ['nullable'],
            'kategori' => ['required', 'string'],
            'kisi_sayisi' => ['nullable', 'integer'],
            'tasarim' => ['nullable', 'string'],
            'mesaj' => ['nullable', 'string', 'max:1000'],
            'ozel_istekler' => ['nullable', 'string', 'max:1000'],
            'odeme_tipi' => ['nullable', 'in:online,fiziksel'],
        ];
    }

    protected function messages(): array
    {
        return [
            'ad_soyad.required' => 'Ad soyad zorunludur.',
            'ad_soyad.min' => 'Ad soyad en az 2 karakter olmalıdır.',
            'telefon.required' => 'Telefon numarası zorunludur.',
            'telefon.phone' => 'Geçerli bir telefon numarası giriniz.',
            'tarih.required' => 'Teslim tarihi zorunludur.',
            'tarih.date' => 'Geçerli bir tarih giriniz (YYYY-MM-DD).',
            'kategori.required' => 'Kategori seçimi zorunludur.',
            'durum.required' => 'Durum zorunludur.',
            'durum.in' => 'Geçersiz sipariş durumu.',
        ];
    }

    /**
     * Ek iş kuralları doğrulaması
     */
    public function validate(array $data): array
    {
        $cleaned = parent::validate($data);

        // Geçmiş tarih kontrolü (sadece create senaryosunda)
        if ($this->scenario === 'create' && !empty($cleaned['tarih'])) {
            $teslimTarihi = strtotime($cleaned['tarih']);
            $bugun = strtotime(date('Y-m-d'));
            if ($teslimTarihi !== false && $teslimTarihi < $bugun) {
                $this->errors['tarih'][] = 'Teslim tarihi geçmiş bir tarih olamaz.';
                throw new \Pastane\Exceptions\ValidationException('Doğrulama hatası.', $this->errors);
            }
        }

        return $cleaned;
    }
}
