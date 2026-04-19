<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;

/**
 * Tema Repository
 *
 * Tema veritabanı işlemleri.
 *
 * @package Pastane\Repositories
 * @since 1.1.0
 */
class TemaRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'site_temalari';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'slug',
        'isim',
        'aciklama',
        'css_dosyasi',
        'js_dosyasi',
        'ikon',
        'aktif',
    ];

    /**
     * @var array Sortable columns whitelist
     */
    protected array $sortableColumns = [
        'id', 'isim', 'slug', 'aktif', 'created_at', 'updated_at',
    ];

    /**
     * Aktif temayı getir
     *
     * @return array|null
     */
    public function findActive(): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE aktif = 1 LIMIT 1";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Tüm temaları deaktif et
     *
     * @return int Etkilenen satır sayısı
     */
    public function deactivateAll(): int
    {
        $sql = "UPDATE {$this->table} SET aktif = 0, updated_at = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }
}
