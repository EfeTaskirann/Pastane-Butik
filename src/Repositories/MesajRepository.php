<?php

declare(strict_types=1);

namespace Pastane\Repositories;

/**
 * Mesaj Repository
 *
 * Mesaj veritabanı işlemleri.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class MesajRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'mesajlar';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'ad',
        'email',
        'telefon',
        'mesaj',
        'okundu',
    ];

    /**
     * @var bool Timestamps
     */
    protected bool $timestamps = true;

    /**
     * Tüm mesajları sıralı getir
     * Önce okunmamışlar, sonra tarihe göre
     *
     * @return array
     */
    public function getAllOrdered(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY okundu ASC, created_at DESC";
        return $this->raw($sql);
    }

    /**
     * Okunmamış mesajları getir
     *
     * @return array
     */
    public function getUnread(): array
    {
        return $this->where(['okundu' => 0], ['*'], 'created_at', 'DESC');
    }

    /**
     * Okunmamış mesaj sayısını getir
     *
     * @return int
     */
    public function getUnreadCount(): int
    {
        return $this->count(['okundu' => 0]);
    }

    /**
     * Mesajı okundu olarak işaretle
     *
     * @param int $id
     * @return bool
     */
    public function markAsRead(int $id): bool
    {
        return $this->update($id, ['okundu' => 1]);
    }

    /**
     * Tüm mesajları okundu olarak işaretle
     *
     * @return int Güncellenen satır sayısı
     */
    public function markAllAsRead(): int
    {
        $sql = "UPDATE {$this->table} SET okundu = 1 WHERE okundu = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
