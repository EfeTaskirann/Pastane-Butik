<?php

declare(strict_types=1);

namespace Pastane\Repositories;

/**
 * Ayar Repository
 *
 * Key-value ayarlar tablosu için CRUD + yardımcı sorgular.
 *
 * Şema referansı: database/migrations/2026_04_17_000002_create_ayarlar_tablosu.php
 *   columns: id, created_by, updated_by, anahtar, deger, tip, grup, aciklama, created_at, updated_at
 *
 * @package Pastane\Repositories
 * @since 2.0.0-sprint2
 */
class AyarRepository extends BaseRepository
{
    /**
     * @var string Tablo adı
     */
    protected string $table = 'ayarlar';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable kolonlar
     */
    protected array $fillable = [
        'anahtar',
        'deger',
        'tip',
        'grup',
        'aciklama',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array ORDER BY whitelist
     */
    protected array $sortableColumns = [
        'id', 'anahtar', 'grup', 'tip', 'created_at', 'updated_at',
    ];

    /**
     * @var bool Timestamps aktif
     */
    protected bool $timestamps = true;

    /**
     * @var array Geçerli tip değerleri (migration ENUM ile birebir)
     */
    public const VALID_TYPES = ['string', 'int', 'bool', 'json'];

    /**
     * Anahtar ile tek ayar getir (raw satır)
     *
     * @param string $anahtar
     * @return array|null
     */
    public function getByKey(string $anahtar): ?array
    {
        return $this->findBy('anahtar', $anahtar);
    }

    /**
     * Tüm grup ayarlarını getir
     *
     * @param string $grup
     * @return array
     */
    public function getByGroup(string $grup): array
    {
        return $this->where(['grup' => $grup], ['*'], 'anahtar', 'ASC');
    }

    /**
     * Tüm ayarları anahtar → row map olarak getir
     *
     * @return array<string, array>
     */
    public function getAllIndexed(): array
    {
        $rows = $this->all(['*'], 'anahtar', 'ASC');
        $out = [];
        foreach ($rows as $row) {
            if (isset($row['anahtar'])) {
                $out[(string) $row['anahtar']] = $row;
            }
        }
        return $out;
    }

    /**
     * Anahtar için değer set et (upsert)
     *
     * Mevcut kayıt varsa `deger` (+ opsiyonel tip/grup/aciklama) günceller,
     * yoksa yeni kayıt oluşturur.
     *
     * @param string      $anahtar
     * @param string      $deger    Raw string değer (JSON için json_encode sonucu)
     * @param string      $tip      string|int|bool|json
     * @param string|null $grup     Grup (null = mevcut değeri koru / yeni kayıtta 'genel')
     * @param string|null $aciklama Açıklama (null = mevcut değeri koru)
     * @return bool
     */
    public function setKey(string $anahtar, string $deger, string $tip = 'string', ?string $grup = null, ?string $aciklama = null): bool
    {
        if (!in_array($tip, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Geçersiz ayar tipi: {$tip}");
        }

        $existing = $this->getByKey($anahtar);

        if ($existing === null) {
            $this->create([
                'anahtar'  => $anahtar,
                'deger'    => $deger,
                'tip'      => $tip,
                'grup'     => $grup ?? 'genel',
                'aciklama' => $aciklama,
            ]);
            return true;
        }

        $data = [
            'deger' => $deger,
            'tip'   => $tip,
        ];
        if ($grup !== null) {
            $data['grup'] = $grup;
        }
        if ($aciklama !== null) {
            $data['aciklama'] = $aciklama;
        }

        return $this->update((int) $existing['id'], $data);
    }

    /**
     * Birden fazla anahtarı tek sorguda getir
     *
     * @param string[] $anahtarlar
     * @return array<string, array> anahtar → row
     */
    public function getByKeys(array $anahtarlar): array
    {
        if (empty($anahtarlar)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($anahtarlar), '?'));
        $sql = "SELECT * FROM `{$this->table}` WHERE `anahtar` IN ({$placeholders})";
        $rows = $this->raw($sql, array_values($anahtarlar));

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['anahtar']] = $row;
        }
        return $out;
    }

    /**
     * Anahtar ile kayıt sil
     *
     * @param string $anahtar
     * @return bool
     */
    public function deleteByKey(string $anahtar): bool
    {
        $row = $this->getByKey($anahtar);
        if ($row === null) {
            return false;
        }
        return $this->delete((int) $row['id']);
    }
}
