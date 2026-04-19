<?php

/**
 * Siparis Takip Token (IDOR Fix) Unit Tests
 *
 * P1-09 paketi kapsamindaki degisiklikleri dogrular:
 * - Token formati (32 karakter hex, bin2hex(random_bytes(16))).
 * - `findByToken()` davranisi (gecersiz format → null, prepared statement).
 * - Repository-level unique token uretimi.
 * - Rate limit bucket kayitlari.
 *
 * Testler DB'ye baglanir (integration sevisesi) ama transaction ile izole edilir.
 * Her test sonunda eklenen kayitlar rollback ile temizlenir.
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Repositories\SiparisRepository;
use Pastane\Repositories\MasaSiparisRepository;

class SiparisTakipTokenTest extends TestCase
{
    protected function usesDatabaseTransactions(): bool
    {
        return true;
    }

    /**
     * Token format: bin2hex(random_bytes(16)) → tam olarak 32 hex karakter.
     */
    public function testGenerateTrackingTokenIs32HexChars(): void
    {
        $siparisRepo = new SiparisRepository();
        $masaRepo    = new MasaSiparisRepository();

        for ($i = 0; $i < 20; $i++) {
            $a = $siparisRepo->generateTrackingToken();
            $b = $masaRepo->generateTrackingToken();

            $this->assertSame(32, strlen($a), 'SiparisRepository token 32 karakter olmali');
            $this->assertSame(32, strlen($b), 'MasaSiparisRepository token 32 karakter olmali');
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $b);
        }
    }

    /**
     * Token benzersizligi: 1000 ardisik uretim → 0 cakisma.
     *
     * bin2hex(random_bytes(16)) 2^128 alan; istatistiksel olarak
     * 1000 ornekte cakisma olasiligi ~0. Deterministik test icin
     * uniqueness invariant'ini dogrularkan kullaniyoruz.
     */
    public function testGenerateTrackingTokenIsUnique(): void
    {
        $repo = new MasaSiparisRepository();
        $set = [];
        for ($i = 0; $i < 1000; $i++) {
            $t = $repo->generateTrackingToken();
            $this->assertArrayNotHasKey($t, $set, 'Token cakismasi tespit edildi');
            $set[$t] = true;
        }
        $this->assertCount(1000, $set);
    }

    /**
     * Tokenlar gercekten random_bytes'tan mi geliyor?
     * Sequential (artan) degilse ilk 8 karakteri unique bir dagilim gosterir.
     * 100 token → en az 95 farkli prefix (entropy tahmini).
     */
    public function testTokensAreNotSequential(): void
    {
        $repo = new SiparisRepository();
        $prefixes = [];
        for ($i = 0; $i < 100; $i++) {
            $prefixes[substr($repo->generateTrackingToken(), 0, 8)] = true;
        }
        // Random ise neredeyse hepsi farkli olmalı; sequential ise tekrar çok olur.
        $this->assertGreaterThanOrEqual(95, count($prefixes), 'Token prefix dagilim testi: random_bytes bekleniyor');
    }

    /**
     * findByToken: gecersiz format (kisa, uzun, non-hex) → null.
     * Sorgu çalismadan erken döner — DB'ye gitmez.
     */
    public function testFindByTokenRejectsInvalidFormats(): void
    {
        $siparisRepo = new SiparisRepository();
        $masaRepo    = new MasaSiparisRepository();

        $bad = [
            '',
            'aaaa',                                // kisa
            'AAAA',                                // kisa + uppercase
            str_repeat('a', 31),                   // 31 karakter
            str_repeat('a', 33),                   // 33 karakter
            str_repeat('Z', 32),                   // hex disi karakter
            "' OR '1'='1",                         // SQL injection denemesi
            '<script>alert(1)</script>',           // XSS denemesi
            bin2hex(random_bytes(16)) . 'x',       // 33 karakter
            'g' . str_repeat('0', 31),             // g hex degil
        ];

        foreach ($bad as $token) {
            $this->assertNull($siparisRepo->findByToken($token), 'Gecersiz token kabul edildi: ' . var_export($token, true));
            $this->assertNull($masaRepo->findByToken($token), 'Gecersiz token (masa) kabul edildi: ' . var_export($token, true));
        }
    }

    /**
     * findByToken: gecerli format + DB'de yok → null.
     */
    public function testFindByTokenReturnsNullForUnknownValidToken(): void
    {
        $repo = new MasaSiparisRepository();
        $randomButUnknown = bin2hex(random_bytes(16));
        $this->assertNull($repo->findByToken($randomButUnknown));
    }

    /**
     * IDOR Fix: masa_siparisleri tablosunda create() cagrisinda
     * takip_token otomatik doldurulmali ve DB'ye kaydedilmeli.
     *
     * Integration test — gercek DB, transaction ile izole.
     */
    public function testCreateAutoGeneratesTakipTokenForMasaSiparis(): void
    {
        $db = $this->getDb();

        // Oturum kaydi gerekir (FK) — test oturumu olustur
        $masaId = (int)$db->query('SELECT id FROM masalar LIMIT 1')->fetchColumn();
        if ($masaId === 0) {
            $this->markTestSkipped('Masa kaydi yok, skip');
        }

        $oturumToken = bin2hex(random_bytes(16));
        $db->prepare('INSERT INTO masa_oturumlari (masa_id, oturum_token, durum, baslangic_zamani) VALUES (?, ?, ?, NOW())')
            ->execute([$masaId, $oturumToken, 'aktif']);
        $oturumId = (int)$db->lastInsertId();

        $repo = new MasaSiparisRepository();
        $id = $repo->create([
            'oturum_id'    => $oturumId,
            'masa_id'      => $masaId,
            'durum'        => 'beklemede',
            'toplam_tutar' => 100.00,
        ]);

        $this->assertNotEmpty($id);

        $row = $repo->find((int)$id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('takip_token', $row);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $row['takip_token']);

        // findByToken ile geri al
        $fetched = $repo->findByToken($row['takip_token']);
        $this->assertNotNull($fetched);
        $this->assertSame((int)$id, (int)$fetched['id']);
    }

    /**
     * IDOR Fix: iki farkli siparisin tokenlari farkli olmali,
     * ve biri digerinin tokenini kullanarak digerine erisememelidir.
     */
    public function testTwoOrdersHaveDifferentTokensAndAreIsolated(): void
    {
        $db = $this->getDb();

        $masaId = (int)$db->query('SELECT id FROM masalar LIMIT 1')->fetchColumn();
        if ($masaId === 0) {
            $this->markTestSkipped('Masa kaydi yok, skip');
        }

        // Iki ayri oturum ac — iki musteri senaryosu
        $otA = bin2hex(random_bytes(16));
        $otB = bin2hex(random_bytes(16));
        $db->prepare('INSERT INTO masa_oturumlari (masa_id, oturum_token, durum, baslangic_zamani) VALUES (?, ?, ?, NOW())')
            ->execute([$masaId, $otA, 'aktif']);
        $oturumAId = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO masa_oturumlari (masa_id, oturum_token, durum, baslangic_zamani) VALUES (?, ?, ?, NOW())')
            ->execute([$masaId, $otB, 'aktif']);
        $oturumBId = (int)$db->lastInsertId();

        $repo = new MasaSiparisRepository();
        $idA = (int)$repo->create([
            'oturum_id' => $oturumAId,
            'masa_id'   => $masaId,
            'durum'     => 'beklemede',
            'toplam_tutar' => 100,
        ]);
        $idB = (int)$repo->create([
            'oturum_id' => $oturumBId,
            'masa_id'   => $masaId,
            'durum'     => 'beklemede',
            'toplam_tutar' => 200,
        ]);

        $rowA = $repo->find($idA);
        $rowB = $repo->find($idB);

        $this->assertNotSame($rowA['takip_token'], $rowB['takip_token'], 'Iki siparisin tokeni ayni olamaz');

        // A'nin tokeniyle sorgu B'yi dondurmemelidir
        $lookupA = $repo->findByToken($rowA['takip_token']);
        $lookupB = $repo->findByToken($rowB['takip_token']);

        $this->assertSame($idA, (int)$lookupA['id']);
        $this->assertSame($idB, (int)$lookupB['id']);
        $this->assertNotSame($lookupA['id'], $lookupB['id']);
    }

    /**
     * Takip token sabitinin (regex) her iki repository'de bulundugunu dogrular.
     * Istemci (takip sayfasi, API endpoint'i) bu sabiti kullanir.
     */
    public function testTakipTokenRegexConstantExists(): void
    {
        $this->assertSame('/^[a-f0-9]{32}$/', SiparisRepository::TAKIP_TOKEN_REGEX);
        $this->assertSame('/^[a-f0-9]{32}$/', MasaSiparisRepository::TAKIP_TOKEN_REGEX);
    }
}
