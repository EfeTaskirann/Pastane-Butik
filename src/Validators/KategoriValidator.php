<?php

declare(strict_types=1);

namespace Pastane\Validators;

/**
 * Kategori Doğrulayıcı
 *
 * Kategori oluşturma/güncelleme verilerini doğrular.
 *
 * @package Pastane\Validators
 * @since 1.0.0
 */
class KategoriValidator extends BaseValidator
{
    private string $scenario;

    public function __construct(string $scenario = 'create')
    {
        $this->scenario = $scenario;
    }

    protected function rules(): array
    {
        if ($this->scenario === 'update') {
            // Update: hiçbir alan zorunlu değil ama gönderilen alanlar valide edilir
            return [
                'ad' => ['nullable', 'string', 'min:2', 'max:100'],
                'slug' => ['nullable', 'string', 'max:100'],
                'aciklama' => ['nullable', 'string', 'max:500'],
                'resim' => ['nullable', 'string', 'max:255'],
                'aktif' => ['nullable', 'boolean'],
                'sira' => ['nullable', 'integer'],
            ];
        }

        return [
            'ad' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'aciklama' => ['nullable', 'string', 'max:500'],
            'resim' => ['nullable', 'string', 'max:255'],
            'aktif' => ['nullable', 'boolean'],
            'sira' => ['nullable', 'integer'],
        ];
    }

    protected function messages(): array
    {
        return [
            'ad.required' => 'Kategori adı zorunludur.',
            'ad.min' => 'Kategori adı en az 2 karakter olmalıdır.',
            'ad.max' => 'Kategori adı en fazla 100 karakter olabilir.',
        ];
    }
}
