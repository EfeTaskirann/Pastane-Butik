<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;

/**
 * Masa Oturum Repository
 *
 * Masa oturumu veritabani islemleri.
 * QR Menu sistemi icin oturum yonetimi.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class MasaOturumRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'masa_oturumlari';

    /**
     * @var bool Timestamps devre disi — bu tabloda created_at/updated_at yok,
     * baslangic_zamani DEFAULT CURRENT_TIMESTAMP ile otomatik olusur
     */
    protected bool $timestamps = false;

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'masa_id',
        'oturum_token',
        'durum',
        'musteri_sayisi',
        'baslangic_zamani',
        'bitis_zamani',
        'toplam_tutar',
        'notlar',
    ];

    /**
     * @var array Sortable columns whitelist
     */
    protected array $sortableColumns = [
        'id', 'masa_id', 'durum', 'baslangic_zamani', 'bitis_zamani',
    ];

    /**
     * Gecerli oturum durumlari
     */
    public const VALID_DURUMLAR = ['aktif', 'tamamlandi', 'iptal'];

    /**
     * Oturum token'ina gore oturum bul
     *
     * @param string $token
     * @return array|null
     */
    public function findByOturumToken(string $token): ?array
    {
        return $this->findBy('oturum_token', $token);
    }

    /**
     * Belirli bir masanin aktif oturumunu getir
     *
     * @param int $masaId
     * @return array|null
     */
    public function getAktifOturum(int $masaId): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE masa_id = ? AND durum = 'aktif'
                ORDER BY baslangic_zamani DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$masaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Oturumu kapat
     *
     * @param int $id Oturum ID
     * @param string $durum Kapanma durumu (tamamlandi veya iptal)
     * @return bool
     */
    public function oturumuKapat(int $id, string $durum = 'tamamlandi'): bool
    {
        if (!in_array($durum, ['tamamlandi', 'iptal'], true)) {
            $durum = 'tamamlandi';
        }

        $sql = "UPDATE {$this->table}
                SET durum = ?, bitis_zamani = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$durum, $id]);
    }

    /**
     * Belirli bir oturuma ait siparisleri getir
     *
     * @param int $oturumId
     * @return array
     */
    public function getOturumSiparisleri(int $oturumId): array
    {
        $sql = "SELECT ms.*
                FROM masa_siparisleri ms
                WHERE ms.oturum_id = ?
                ORDER BY ms.siparis_zamani DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$oturumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Uzun süredir aktivite olmayan oturumlari otomatik kapat
     *
     * "Aktivite" olarak oturumun kendi baslangic_zamani ve oturuma bagli
     * en son siparis (masa_siparisleri.siparis_zamani) dikkate alinir.
     * Her ikisi de $timeoutMinutes dakikadan eski ise oturum 'tamamlandi' olarak isaretlenir.
     *
     * Iliskili masalarin durum/aktif_oturum_id alanlari da temizlenir
     * (FK ON DELETE SET NULL degil, app-level temizlik).
     *
     * Cron job: bin/cron/masa-timeout.php
     *
     * @param int $timeoutMinutes Varsayilan 180 dakika (3 saat)
     * @return array{closed_sessions: int, freed_tables: int, session_ids: array<int>}
     */
    public function closeInactiveSessions(int $timeoutMinutes = 180): array
    {
        if ($timeoutMinutes < 1) {
            $timeoutMinutes = 180;
        }

        // 1. Inaktif aktif oturumlari bul
        //    - durum = 'aktif'
        //    - baslangic_zamani > $timeoutMinutes dakika once
        //    - oturuma bagli son siparis de $timeoutMinutes'tan eski (ya da hic yok)
        $findSql = "
            SELECT o.id AS oturum_id, o.masa_id
            FROM {$this->table} o
            LEFT JOIN (
                SELECT oturum_id, MAX(siparis_zamani) AS son_siparis
                FROM masa_siparisleri
                GROUP BY oturum_id
            ) s ON s.oturum_id = o.id
            WHERE o.durum = 'aktif'
              AND o.baslangic_zamani < (NOW() - INTERVAL ? MINUTE)
              AND (s.son_siparis IS NULL OR s.son_siparis < (NOW() - INTERVAL ? MINUTE))
        ";

        $stmt = $this->db->prepare($findSql);
        $stmt->execute([$timeoutMinutes, $timeoutMinutes]);
        $inaktifler = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($inaktifler)) {
            return ['closed_sessions' => 0, 'freed_tables' => 0, 'session_ids' => []];
        }

        $sessionIds = array_map(static fn($r) => (int)$r['oturum_id'], $inaktifler);
        $masaIds    = array_map(static fn($r) => (int)$r['masa_id'], $inaktifler);
        $masaIds    = array_values(array_unique(array_filter($masaIds, static fn($id) => $id > 0)));

        // 2. Transaction: oturumlari kapat + masalari bosalt
        // $sessionIds yukarida empty check'i yapildigi icin burada dolu garanti edilir;
        // $masaIds filtrelendikten sonra bos olabilir, bu nedenle ayri kontrol edilir.
        return $this->transaction(function () use ($sessionIds, $masaIds) {
            $closed = 0;
            $freed  = 0;

            // Oturumlari kapat (sessionIds yukarida garanti dolu)
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $closeSql = "UPDATE {$this->table}
                         SET durum = 'tamamlandi',
                             bitis_zamani = NOW(),
                             notlar = CONCAT(COALESCE(notlar, ''), IF(notlar IS NULL OR notlar = '', '', ' | '), '[Otomatik kapatildi - inaktivite timeout]')
                         WHERE id IN ({$placeholders})
                           AND durum = 'aktif'";
            $closeStmt = $this->db->prepare($closeSql);
            $closeStmt->execute($sessionIds);
            $closed = $closeStmt->rowCount();

            // Masalari bosalt (aktif_oturum_id = NULL, durum = 'bos')
            if (!empty($masaIds)) {
                $placeholders = implode(',', array_fill(0, count($masaIds), '?'));
                $masaSql = "UPDATE masalar
                            SET aktif_oturum_id = NULL,
                                durum = 'bos',
                                guncelleme_tarihi = NOW()
                            WHERE id IN ({$placeholders})
                              AND durum = 'aktif'";
                $masaStmt = $this->db->prepare($masaSql);
                $masaStmt->execute($masaIds);
                $freed = $masaStmt->rowCount();
            }

            return [
                'closed_sessions' => $closed,
                'freed_tables'    => $freed,
                'session_ids'     => $sessionIds,
            ];
        });
    }
}
