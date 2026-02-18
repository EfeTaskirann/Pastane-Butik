<?php

declare(strict_types=1);

namespace Pastane\Validators;

/**
 * Auth Doğrulayıcı
 *
 * Kimlik doğrulama verilerini doğrular.
 *
 * @package Pastane\Validators
 * @since 1.0.0
 */
class AuthValidator extends BaseValidator
{
    private string $scenario;

    public function __construct(string $scenario = 'login')
    {
        $this->scenario = $scenario;
    }

    protected function rules(): array
    {
        return match ($this->scenario) {
            'login' => [
                'kullanici_adi' => ['required', 'string', 'min:2', 'max:50'],
                'sifre' => ['required', 'string'],
                'two_factor_code' => ['nullable', 'string'],
            ],
            'change_password' => [
                'mevcut_sifre' => ['required', 'string'],
                'yeni_sifre' => ['required', 'string', 'min:8'],
            ],
            '2fa_verify' => [
                'code' => ['required', 'string'],
            ],
            '2fa_disable' => [
                'sifre' => ['required', 'string'],
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'kullanici_adi.required' => 'Kullanıcı adı zorunludur.',
            'sifre.required' => 'Şifre zorunludur.',
            'mevcut_sifre.required' => 'Mevcut şifre zorunludur.',
            'yeni_sifre.required' => 'Yeni şifre zorunludur.',
            'yeni_sifre.min' => 'Yeni şifre en az 8 karakter olmalıdır.',
            'code.required' => 'Doğrulama kodu zorunludur.',
        ];
    }
}
