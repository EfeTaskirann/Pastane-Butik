#!/usr/bin/env php
<?php
/**
 * Database Seed Script — Fresh install icin ornek veri
 *
 * Sprint 4 (Production Launch). Calisma prensibi:
 *   - Tum INSERT'ler `INSERT IGNORE` veya `ON DUPLICATE KEY UPDATE` ile idempotent
 *   - Boylece tekrar tekrar calistirilabilir, duplicate key hatasi vermez
 *   - Admin sifresi ENV `SEED_ADMIN_PASSWORD` yoksa guvenli rastgele olarak uretilir
 *   - Masalar: QR token `random_bytes(32)` ile urretilir (mevcut masalar guncellenmez)
 *
 * Kullanim:
 *   php bin/db-seed.php                     # Tum seed
 *   php bin/db-seed.php --skip-admin        # Admin kullaniciyi atlar
 *   SEED_ADMIN_USERNAME=admin SEED_ADMIN_PASSWORD=xxx php bin/db-seed.php
 *
 * @package Pastane
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$args = array_slice($argv, 1);
$skipAdmin = in_array('--skip-admin', $args, true);
$quiet     = in_array('--quiet', $args, true);

function seed_log(string $msg, string $type = 'info', bool $quiet = false): void
{
    if ($quiet) {
        return;
    }
    $colors = [
        'info'    => "\033[0;36m",
        'success' => "\033[0;32m",
        'error'   => "\033[0;31m",
        'warning' => "\033[0;33m",
        'reset'   => "\033[0m",
    ];
    $c = $colors[$type] ?? $colors['info'];
    echo $c . $msg . $colors['reset'] . PHP_EOL;
}

$pdo = \Pastane\Database\Database::getInstance()->getPdo();
$startTime = microtime(true);
$stats = ['kategoriler' => 0, 'urunler' => 0, 'masalar' => 0, 'admin' => 0, 'ayarlar' => 0];

seed_log('==> Pastane DB Seed baslıyor...', 'info', $quiet);

try {
    // =========================================================================
    // 1. KATEGORILER (5 adet)
    // =========================================================================
    seed_log('  [1/5] Kategoriler seed ediliyor...', 'info', $quiet);
    $kategoriler = [
        ['isim' => 'Pastalar',    'slug' => 'pastalar',    'sira' => 1],
        ['isim' => 'Kurabiyeler', 'slug' => 'kurabiyeler', 'sira' => 2],
        ['isim' => 'Tatlılar',    'slug' => 'tatlilar',    'sira' => 3],
        ['isim' => 'İçecekler',   'slug' => 'icecekler',   'sira' => 4],
        ['isim' => 'Sandviçler',  'slug' => 'sandvicler',  'sira' => 5],
    ];

    $kategoriStmt = $pdo->prepare(
        "INSERT INTO kategoriler (isim, slug, sira) VALUES (:isim, :slug, :sira)
         ON DUPLICATE KEY UPDATE isim = VALUES(isim), sira = VALUES(sira)"
    );
    foreach ($kategoriler as $k) {
        $kategoriStmt->execute($k);
        $stats['kategoriler']++;
    }

    // slug -> id map (urunler icin)
    $slugMap = [];
    foreach ($pdo->query("SELECT id, slug FROM kategoriler") as $row) {
        $slugMap[$row['slug']] = (int) $row['id'];
    }

    // =========================================================================
    // 2. URUNLER (15 adet, her kategori icin 3)
    // =========================================================================
    seed_log('  [2/5] Urunler seed ediliyor...', 'info', $quiet);
    $urunler = [
        // Pastalar
        ['slug' => 'pastalar',    'isim' => 'Çilekli Yaş Pasta',           'aciklama' => 'Taze çilek ve hafif kremalı yaş pasta.',  'fiyat' => 450.00, 'sira' => 1],
        ['slug' => 'pastalar',    'isim' => 'Çikolatalı Doğum Günü Pastası', 'aciklama' => 'Yoğun çikolatalı özel kutlama pastası.', 'fiyat' => 500.00, 'sira' => 2],
        ['slug' => 'pastalar',    'isim' => 'Frambuazlı Cheesecake',       'aciklama' => 'New York usulü, frambuaz soslu.',          'fiyat' => 380.00, 'sira' => 3],
        // Kurabiyeler
        ['slug' => 'kurabiyeler', 'isim' => 'Butik Kurabiye Seti (12 adet)', 'aciklama' => 'El yapımı, dekoratif kurabiyeler.',    'fiyat' => 180.00, 'sira' => 1],
        ['slug' => 'kurabiyeler', 'isim' => 'Çikolatalı Cookie',           'aciklama' => 'Damla çikolatalı klasik cookie.',          'fiyat' => 35.00,  'sira' => 2],
        ['slug' => 'kurabiyeler', 'isim' => 'Bademli Kurabiye',            'aciklama' => 'Çıtır badem parçacıklı kurabiye.',         'fiyat' => 40.00,  'sira' => 3],
        // Tatlilar
        ['slug' => 'tatlilar',    'isim' => 'Tiramisu',                    'aciklama' => 'İtalyan usulü kahveli tatlı.',             'fiyat' => 120.00, 'sira' => 1],
        ['slug' => 'tatlilar',    'isim' => 'Profiterol',                  'aciklama' => 'Çikolata soslu klasik profiterol.',        'fiyat' => 95.00,  'sira' => 2],
        ['slug' => 'tatlilar',    'isim' => 'Sütlaç',                      'aciklama' => 'Fırın sütlaç, tarçınlı.',                  'fiyat' => 70.00,  'sira' => 3],
        // Icecekler
        ['slug' => 'icecekler',   'isim' => 'Türk Kahvesi',                'aciklama' => 'Geleneksel usul, lokumla.',                'fiyat' => 55.00,  'sira' => 1],
        ['slug' => 'icecekler',   'isim' => 'Sıcak Çikolata',              'aciklama' => 'Belçika çikolatası, marshmallow ile.',     'fiyat' => 70.00,  'sira' => 2],
        ['slug' => 'icecekler',   'isim' => 'Limonata',                    'aciklama' => 'Ev yapımı taze limonata.',                 'fiyat' => 50.00,  'sira' => 3],
        // Sandvicler
        ['slug' => 'sandvicler',  'isim' => 'Club Sandviç',                'aciklama' => 'Tavuk, bacon, marul, domates.',            'fiyat' => 150.00, 'sira' => 1],
        ['slug' => 'sandvicler',  'isim' => 'Tost (Kaşarlı)',              'aciklama' => 'Kaşar ve sucuklu tost.',                   'fiyat' => 85.00,  'sira' => 2],
        ['slug' => 'sandvicler',  'isim' => 'Avokadolu Toast',             'aciklama' => 'Tam buğday ekmek, avokado, rukola.',       'fiyat' => 130.00, 'sira' => 3],
    ];

    // Not: `urunler` tablosunda `isim` UNIQUE degil, bu yuzden kategori_id+isim kombinasyonuna
    // bakarak idempotent update yapiyoruz (INSERT INTO ... WHERE NOT EXISTS sub-select).
    $urunExistsStmt = $pdo->prepare(
        "SELECT id FROM urunler WHERE kategori_id = ? AND isim = ? LIMIT 1"
    );
    $urunInsertStmt = $pdo->prepare(
        "INSERT INTO urunler (kategori_id, isim, aciklama, fiyat, aktif, sira)
         VALUES (:kategori_id, :isim, :aciklama, :fiyat, 1, :sira)"
    );
    $urunUpdateStmt = $pdo->prepare(
        "UPDATE urunler SET aciklama = :aciklama, fiyat = :fiyat, sira = :sira WHERE id = :id"
    );

    foreach ($urunler as $u) {
        $kategoriId = $slugMap[$u['slug']] ?? null;
        if ($kategoriId === null) {
            seed_log("    - Kategori bulunamadi: {$u['slug']}, atlandi", 'warning', $quiet);
            continue;
        }
        $urunExistsStmt->execute([$kategoriId, $u['isim']]);
        $existingId = $urunExistsStmt->fetchColumn();
        if ($existingId !== false) {
            $urunUpdateStmt->execute([
                'id'       => (int) $existingId,
                'aciklama' => $u['aciklama'],
                'fiyat'    => $u['fiyat'],
                'sira'     => $u['sira'],
            ]);
        } else {
            $urunInsertStmt->execute([
                'kategori_id' => $kategoriId,
                'isim'        => $u['isim'],
                'aciklama'    => $u['aciklama'],
                'fiyat'       => $u['fiyat'],
                'sira'        => $u['sira'],
            ]);
        }
        $stats['urunler']++;
    }

    // =========================================================================
    // 3. ADMIN KULLANICI (1 adet, guvenli sifre)
    // =========================================================================
    if (!$skipAdmin) {
        seed_log('  [3/5] Admin kullanici seed ediliyor...', 'info', $quiet);
        $adminUsername = getenv('SEED_ADMIN_USERNAME') ?: 'admin';
        $adminPassword = getenv('SEED_ADMIN_PASSWORD') ?: null;

        $generatedPw = false;
        if ($adminPassword === null || $adminPassword === '') {
            // Guvenli rastgele sifre uret — 16 karakter base64url
            $adminPassword = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
            $generatedPw = true;
        }
        $hash = password_hash($adminPassword, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            "INSERT INTO admin_kullanicilar (kullanici_adi, sifre_hash)
             VALUES (:u, :h)
             ON DUPLICATE KEY UPDATE kullanici_adi = VALUES(kullanici_adi)"
        );
        $stmt->execute(['u' => $adminUsername, 'h' => $hash]);
        $stats['admin']++;

        if ($generatedPw) {
            seed_log("    Admin olusturuldu (veya mevcut):", 'success', $quiet);
            seed_log("      kullanici: {$adminUsername}", 'success', $quiet);
            seed_log("      sifre:     {$adminPassword}  <-- BIR KERE GORUNTULENIR, KAYDEDIN!", 'warning', $quiet);
            seed_log("    Not: Mevcut admin'in sifresi DEGISTIRILMEDI (ON DUPLICATE guncellemedi).", 'info', $quiet);
            seed_log("    Sifre reset icin: cli/create-admin.php kullanin.", 'info', $quiet);
        } else {
            seed_log("    Admin '{$adminUsername}' seed edildi (env'den).", 'success', $quiet);
        }
    } else {
        seed_log('  [3/5] Admin kullanici atlandi (--skip-admin).', 'warning', $quiet);
    }

    // =========================================================================
    // 4. MASALAR (10 adet, unique QR token ile)
    // =========================================================================
    seed_log('  [4/5] Masalar seed ediliyor...', 'info', $quiet);
    $masaExistsStmt = $pdo->prepare("SELECT id FROM masalar WHERE masa_no = ? LIMIT 1");
    $masaInsertStmt = $pdo->prepare(
        "INSERT INTO masalar (masa_no, qr_token, durum, kapasite)
         VALUES (:masa_no, :qr_token, 'bos', :kapasite)"
    );

    for ($i = 1; $i <= 10; $i++) {
        $masaExistsStmt->execute([$i]);
        if ($masaExistsStmt->fetchColumn() !== false) {
            continue; // mevcut masa, atla (QR token sabit kalsin)
        }
        // QR token unique — bin2hex(random_bytes(32)) = 64 hex char
        $qrToken = bin2hex(random_bytes(32));
        $kapasite = ($i <= 4) ? 2 : (($i <= 8) ? 4 : 6);
        $masaInsertStmt->execute([
            'masa_no'  => $i,
            'qr_token' => $qrToken,
            'kapasite' => $kapasite,
        ]);
        $stats['masalar']++;
    }

    // =========================================================================
    // 5. AYARLAR (Sprint 2 AyarService ile uyumlu varsayilan ayarlar)
    // =========================================================================
    seed_log('  [5/5] Ayarlar seed ediliyor...', 'info', $quiet);

    // `ayarlar` tablosu migration 2024_01_01_000001 ile olusturuldu; tip ENUM eski sema:
    // 'text','textarea','number','boolean','json'. Yeni migration 2026_04_17_000002 bunu
    // 'string','int','bool','json' olarak genisletir. Uygulamayi desteklemek icin tip
    // degerini ayarlar tablosundaki mevcut ENUM'a gore uyarlamak gerekir.
    // COLUMN_TYPE kontrolu yaparak dogru tip degerini seciyoruz.
    $colCheck = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'ayarlar' AND column_name = 'tip'"
    );
    $colType = (string) ($colCheck->fetchColumn() ?: '');
    $hasNewEnum = str_contains($colType, "'string'");

    $ayarlar = [
        ['anahtar' => 'site_adi',          'deger' => 'Pastane',                    'tip_yeni' => 'string', 'tip_eski' => 'text',     'grup' => 'genel', 'aciklama' => 'Site adi'],
        ['anahtar' => 'site_email',        'deger' => 'iletisim@pastane.local',     'tip_yeni' => 'string', 'tip_eski' => 'text',     'grup' => 'genel', 'aciklama' => 'Iletisim e-postasi'],
        ['anahtar' => 'site_telefon',      'deger' => '+90 555 000 0000',           'tip_yeni' => 'string', 'tip_eski' => 'text',     'grup' => 'genel', 'aciklama' => 'Iletisim telefonu'],
        ['anahtar' => 'adres',             'deger' => 'Seed Mahallesi, No:1',       'tip_yeni' => 'string', 'tip_eski' => 'textarea', 'grup' => 'genel', 'aciklama' => 'Isletme adresi'],
        ['anahtar' => 'calisma_saatleri',  'deger' => '09:00 - 22:00',              'tip_yeni' => 'string', 'tip_eski' => 'text',     'grup' => 'genel', 'aciklama' => 'Calisma saatleri'],
        ['anahtar' => 'para_birimi',       'deger' => 'TRY',                        'tip_yeni' => 'string', 'tip_eski' => 'text',     'grup' => 'odeme', 'aciklama' => 'Para birimi kodu'],
        ['anahtar' => 'kdv_orani',         'deger' => '10',                         'tip_yeni' => 'int',    'tip_eski' => 'number',   'grup' => 'odeme', 'aciklama' => 'KDV orani (%)'],
        ['anahtar' => 'qr_menu_aktif',     'deger' => '1',                          'tip_yeni' => 'bool',   'tip_eski' => 'boolean',  'grup' => 'qr',    'aciklama' => 'QR menu sistemi aktif mi'],
        ['anahtar' => 'masa_timeout_dk',   'deger' => '120',                        'tip_yeni' => 'int',    'tip_eski' => 'number',   'grup' => 'qr',    'aciklama' => 'Masa oturum timeout dakika'],
    ];

    $ayarStmt = $pdo->prepare(
        "INSERT INTO ayarlar (anahtar, deger, tip, grup, aciklama)
         VALUES (:anahtar, :deger, :tip, :grup, :aciklama)
         ON DUPLICATE KEY UPDATE deger = VALUES(deger), grup = VALUES(grup), aciklama = VALUES(aciklama)"
    );

    foreach ($ayarlar as $a) {
        $ayarStmt->execute([
            'anahtar'  => $a['anahtar'],
            'deger'    => $a['deger'],
            'tip'      => $hasNewEnum ? $a['tip_yeni'] : $a['tip_eski'],
            'grup'     => $a['grup'],
            'aciklama' => $a['aciklama'],
        ]);
        $stats['ayarlar']++;
    }

    // =========================================================================
    // Ozet
    // =========================================================================
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);

    seed_log('', 'info', $quiet);
    seed_log('==> Seed tamamlandi:', 'success', $quiet);
    seed_log(sprintf('    Kategoriler : %d', $stats['kategoriler']), 'success', $quiet);
    seed_log(sprintf('    Urunler     : %d', $stats['urunler']),     'success', $quiet);
    seed_log(sprintf('    Admin       : %d', $stats['admin']),       'success', $quiet);
    seed_log(sprintf('    Masalar     : %d (yeni eklenen)', $stats['masalar']), 'success', $quiet);
    seed_log(sprintf('    Ayarlar     : %d', $stats['ayarlar']),     'success', $quiet);
    seed_log(sprintf('    Sure        : %s ms', $elapsed),           'info', $quiet);
    seed_log('', 'info', $quiet);
    seed_log('Not: Script idempotent — tekrar tekrar calistirilabilir (INSERT IGNORE / ON DUPLICATE KEY).', 'info', $quiet);
    exit(0);
} catch (\Throwable $e) {
    seed_log('HATA: ' . $e->getMessage(), 'error', $quiet);
    seed_log('Dosya: ' . $e->getFile() . ':' . $e->getLine(), 'error', $quiet);
    exit(1);
}
