<?php

declare(strict_types=1);

namespace Pastane\Validators;

use Pastane\Exceptions\ValidationException;

/**
 * Base Validator
 *
 * Genel doğrulama altyapısı. Tüm validator'lar bu sınıfı extend eder.
 *
 * @package Pastane\Validators
 * @since 1.0.0
 */
abstract class BaseValidator
{
    /**
     * @var array Doğrulama hataları
     */
    protected array $errors = [];

    /**
     * @var array Doğrulanacak veri
     */
    protected array $data = [];

    /**
     * Doğrulama kurallarını döndür
     *
     * @return array ['alan' => ['kural1', 'kural2']]
     */
    abstract protected function rules(): array;

    /**
     * Özel hata mesajları (override edilebilir)
     *
     * @return array ['alan.kural' => 'Hata mesajı']
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Veriyi doğrula
     *
     * @param array $data
     * @return array Temizlenmiş veri
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $this->data = $data;
        $this->errors = [];

        foreach ($this->rules() as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            $value = $data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Doğrulama hatası.', $this->errors);
        }

        // Sadece rules'da tanımlı alanları döndür (whitelist)
        return array_intersect_key($data, $this->rules());
    }

    /**
     * Tekil kural uygula
     */
    protected function applyRule(string $field, mixed $value, string $rule): void
    {
        // Parametreli kuralları ayır: "min:3" → ["min", "3"]
        $params = [];
        if (str_contains($rule, ':')) {
            [$rule, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }

        $method = 'rule' . ucfirst($rule);

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
    }

    /**
     * Hata ekle
     */
    protected function addError(string $field, string $rule, string $defaultMessage): void
    {
        $customKey = "{$field}.{$rule}";
        $messages = $this->messages();

        $this->errors[$field][] = $messages[$customKey] ?? $defaultMessage;
    }

    // ========================================
    // BUILT-IN VALIDATION RULES
    // ========================================

    protected function ruleRequired(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->addError($field, 'required', "{$field} alanı zorunludur.");
        }
    }

    protected function ruleString(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, 'string', "{$field} alanı metin olmalıdır.");
        }
    }

    protected function ruleNumeric(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($field, 'numeric', "{$field} alanı sayısal olmalıdır.");
        }
    }

    protected function ruleInteger(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !is_int($value) && !ctype_digit((string)$value)) {
            $this->addError($field, 'integer', "{$field} alanı tam sayı olmalıdır.");
        }
    }

    protected function ruleEmail(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', "{$field} alanı geçerli bir e-posta olmalıdır.");
        }
    }

    protected function ruleMin(string $field, mixed $value, array $params): void
    {
        $min = (int)($params[0] ?? 0);

        if (is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, 'min', "{$field} alanı en az {$min} karakter olmalıdır.");
        } elseif (is_numeric($value) && (float)$value < $min) {
            $this->addError($field, 'min', "{$field} alanı en az {$min} olmalıdır.");
        }
    }

    protected function ruleMax(string $field, mixed $value, array $params): void
    {
        $max = (int)($params[0] ?? 0);

        if (is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, 'max', "{$field} alanı en fazla {$max} karakter olmalıdır.");
        } elseif (is_numeric($value) && (float)$value > $max) {
            $this->addError($field, 'max', "{$field} alanı en fazla {$max} olmalıdır.");
        }
    }

    protected function ruleIn(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !in_array($value, $params, true)) {
            $this->addError($field, 'in', "{$field} alanı geçersiz. İzin verilenler: " . implode(', ', $params));
        }
    }

    protected function ruleDate(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '') {
            $format = $params[0] ?? 'Y-m-d';
            $d = \DateTime::createFromFormat($format, (string)$value);
            if (!$d || $d->format($format) !== $value) {
                $this->addError($field, 'date', "{$field} alanı geçerli bir tarih olmalıdır ({$format}).");
            }
        }
    }

    protected function rulePhone(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && function_exists('validate_phone') && !validate_phone($value)) {
            $this->addError($field, 'phone', "{$field} alanı geçerli bir telefon numarası olmalıdır.");
        }
    }

    protected function ruleNullable(string $field, mixed $value, array $params): void
    {
        // nullable kuralı: değer null ise diğer kuralları atla
        // Bu kural başka kurallardan önce gelmelidir
    }

    protected function ruleBoolean(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            $this->addError($field, 'boolean', "{$field} alanı doğru veya yanlış olmalıdır.");
        }
    }

    /**
     * Hataları al
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
