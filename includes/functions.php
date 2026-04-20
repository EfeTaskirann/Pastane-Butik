<?php
declare(strict_types=1);

/**
 * Yardımcı Fonksiyonlar
 *
 * Bu dosya geriye uyumluluk için korunmuştur.
 * Yeni kodda helpers.php fonksiyonlarını veya service'leri kullanın.
 *
 * Eşdeğer helpers.php fonksiyonları:
 * - e() -> e() (aynı)
 * - formatPrice() -> format_price()
 * - redirect() -> redirect()
 */

require_once __DIR__ . '/db.php';

// ==========================================
// GENEL YARDIMCI FONKSİYONLAR
// ==========================================

/**
 * Takvim yoğunluk durumu
 * Puana göre CSS class döndürür
 *
 * @param int $puan İş yükü puanı
 * @return array{class: string, label: string}
 * @deprecated SiparisService::getWorkloadCategory() kullanın
 */
if (!function_exists('getYogunlukDurumu')) {
    function getYogunlukDurumu($puan) {
        // Eşik değerleri SiparisService sabitleriyle uyumlu tutulmalıdır
        if ($puan <= \Pastane\Services\SiparisService::WORKLOAD_BOS) {
            return ['class' => 'bos', 'label' => 'Boş'];
        } elseif ($puan <= \Pastane\Services\SiparisService::WORKLOAD_UYGUN) {
            return ['class' => 'uygun', 'label' => 'Uygun'];
        } elseif ($puan <= \Pastane\Services\SiparisService::WORKLOAD_YOGUN) {
            return ['class' => 'yogun', 'label' => 'Yoğun'];
        } else {
            return ['class' => 'cok-yogun', 'label' => 'Çok Yoğun'];
        }
    }
}

/**
 * XSS Koruması
 * @see helpers.php e() fonksiyonu (eşdeğer)
 */
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Fiyat formatlama
 * @see helpers.php format_price() fonksiyonu (eşdeğer)
 */
if (!function_exists('formatPrice')) {
    /**
     * PHP 8.1+ ile birlikte number_format() implicit string→float conversion'i
     * deprecated, strict_types altinda fatal. PDO DECIMAL kolonlari string olarak
     * dondugu icin (PDO::ATTR_STRINGIFY_FETCHES = false olsa bile) explicit cast
     * sart.
     */
    function formatPrice($price) {
        if ($price === null || $price === '') {
            return '0,00 ₺';
        }
        return number_format((float) $price, 2, ',', '.') . ' ₺';
    }
}

/**
 * Redirect
 * @see helpers.php redirect() fonksiyonu (eşdeğer)
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit;
    }
}

// ==========================================
// AUDIT / KULLANICI KİMLİĞİ HELPER
// ==========================================

/**
 * Mevcut oturumdaki admin kullanıcı ID'sini döndür
 *
 * Audit trail (created_by / updated_by) kolonlarının otomatik doldurulması için.
 * Oturum yoksa veya admin_id tanımsızsa null döner (repository'ler null geçebilmeli).
 *
 * @return int|null Admin kullanıcı ID'si veya null
 */
if (!function_exists('auth_user_id')) {
    function auth_user_id(): ?int
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            // Session açık değilse sessizce null dön — CLI ve test ortamında sorun çıkarmasın
            return null;
        }

        if (!isset($_SESSION['admin_id'])) {
            return null;
        }

        $id = (int) $_SESSION['admin_id'];
        return $id > 0 ? $id : null;
    }
}

// ==========================================
// AYAR (SETTINGS) HELPER
// ==========================================

/**
 * Ayar servisinden değer al (tip-aware)
 *
 * Kullanım:
 *   ayar('site_baslik')                     // string | null
 *   ayar('iletisim_email', 'info@a.com')    // default fallback
 *   ayar('kapida_odeme_aktif', false)       // bool cast
 *
 * @param string $key     Ayar anahtarı
 * @param mixed  $default Anahtar yoksa dönecek değer
 * @return mixed
 */
if (!function_exists('ayar')) {
    function ayar(string $key, mixed $default = null): mixed
    {
        try {
            /** @var \Pastane\Services\AyarService $service */
            $service = ayar_service();
            return $service->get($key, $default);
        } catch (\Throwable) {
            // Veritabanı/servis hatası: default'a düş
            return $default;
        }
    }
}

/**
 * AyarService singleton
 *
 * @return \Pastane\Services\AyarService
 */
if (!function_exists('ayar_service')) {
    function ayar_service(): \Pastane\Services\AyarService
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new \Pastane\Services\AyarService();
        }
        return $instance;
    }
}

// ==========================================
// RBAC / PERMISSION HELPER
// ==========================================

/**
 * Permission service singleton
 *
 * @return \Pastane\Services\PermissionService
 */
if (!function_exists('permission_service')) {
    function permission_service(): \Pastane\Services\PermissionService
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new \Pastane\Services\PermissionService();
        }
        return $instance;
    }
}

/**
 * Mevcut oturumdaki kullanıcının belirli izni var mı?
 *
 * @param string $permission İzin adı (örn. 'product.view')
 * @return bool
 */
if (!function_exists('user_can')) {
    function user_can(string $permission): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
        if ($userId <= 0) {
            return false;
        }
        return permission_service()->hasPermission($userId, $permission);
    }
}

/**
 * Belirli izni zorunlu kıl — aksi halde 403 döndür ve çıkış.
 *
 * Admin sayfaları için basit koruma:
 *   require_permission('product.view');
 *
 * Davranış:
 *   - Oturum yoksa → admin/index.php (login) sayfasına yönlendirir.
 *   - Oturum var ama yetki yok → 403 durum kodu + admin/403.php render.
 *
 * @param string $permission İzin adı
 * @return void
 */
if (!function_exists('require_permission')) {
    function require_permission(string $permission): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;

        if ($userId <= 0) {
            // Login'e yönlendir
            if (!headers_sent()) {
                header('Location: ' . (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/admin/index.php');
            }
            exit;
        }

        if (permission_service()->hasPermission($userId, $permission)) {
            return;
        }

        // 403 — yetki yok
        if (!headers_sent()) {
            http_response_code(403);
        }

        $forbiddenPage = __DIR__ . '/../admin/403.php';
        if (file_exists($forbiddenPage)) {
            // Permission adını context olarak geçir
            $requiredPermission = $permission;
            include $forbiddenPage;
        } else {
            echo '403 — Bu işlem için yetkiniz yok.';
        }
        exit;
    }
}

// ==========================================
// FLASH MESAJ FONKSİYONLARI
// ==========================================

/**
 * Flash mesaj ayarla
 * @see helpers.php flash() fonksiyonu
 */
if (!function_exists('setFlash')) {
    function setFlash($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

/**
 * Flash mesaj al ve temizle
 */
if (!function_exists('getFlash')) {
    function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}

// ==========================================
// KATEGORİ VE ÜRÜN FONKSİYONLARI
// @deprecated Yeni kodda kategori_service() ve urun_service() kullanın
// ==========================================

/**
 * Kategorileri getir
 * @deprecated kategori_service()->all() kullanın
 */
if (!function_exists('getCategories')) {
    function getCategories() {
        return Cache::getInstance()->remember('categories_all', function () {
            return db()->fetchAll("SELECT * FROM kategoriler ORDER BY sira ASC, isim ASC");
        }, 3600); // 1 saat cache
    }
}

/**
 * Aktif ürünleri getir
 * @deprecated urun_service()->getActive() kullanın
 */
if (!function_exists('getProducts')) {
    function getProducts($kategori_id = null) {
        $cacheKey = 'products_active' . ($kategori_id ? "_{$kategori_id}" : '');

        return Cache::getInstance()->remember($cacheKey, function () use ($kategori_id) {
            $sql = "SELECT u.*, k.isim as kategori_ad, k.slug as kategori_slug
                    FROM urunler u
                    LEFT JOIN kategoriler k ON u.kategori_id = k.id
                    WHERE u.aktif = 1";
            $params = [];

            if ($kategori_id) {
                $sql .= " AND u.kategori_id = :kategori_id";
                $params['kategori_id'] = $kategori_id;
            }

            $sql .= " ORDER BY u.sira ASC, u.created_at DESC";
            return db()->fetchAll($sql, $params);
        }, 1800); // 30 dk cache
    }
}

// ==========================================
// GÖRSEL YÖNETİMİ
// ==========================================

/**
 * Görsel silme
 */
if (!function_exists('deleteImage')) {
    function deleteImage($filename) {
        $path = (defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads/') . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

// ==========================================
// MESAJ FONKSİYONLARI (MesajService wrapper)
// ==========================================

/**
 * Okunmamış mesaj sayısı
 * @deprecated mesaj_service()->getUnreadCount() kullanın
 */
if (!function_exists('getUnreadMessageCount')) {
    function getUnreadMessageCount() {
        return Cache::getInstance()->remember('unread_message_count', function () {
            // mesaj_service() tanımlıysa onu kullan, değilse fallback
            if (function_exists('mesaj_service')) {
                try {
                    return mesaj_service()->getUnreadCount();
                } catch (Throwable) {
                    // Service hatası durumunda fallback
                }
            }
            $result = db()->fetch("SELECT COUNT(*) as count FROM mesajlar WHERE okundu = 0");
            return $result['count'] ?? 0;
        }, 30); // 30 saniye cache — admin panel'de sık erişilir
    }
}

// ==========================================
// RAPOR FONKSİYONLARI (RaporService wrapper'ları)
// @deprecated Yeni kodda rapor_service() kullanın
// ==========================================

/**
 * Aylık satış özeti
 * @deprecated rapor_service()->getSatisOzeti() kullanın
 */
if (!function_exists('getSatisOzeti')) {
    function getSatisOzeti($baslangic, $bitis) {
        return rapor_service()->getSatisOzeti($baslangic, $bitis);
    }
}

/**
 * Geçen ay ile karşılaştırma (% değişim)
 * @deprecated rapor_service()->getGecenAyKarsilastirma() kullanın
 */
if (!function_exists('getGecenAyKarsilastirma')) {
    function getGecenAyKarsilastirma($baslangic, $bitis) {
        return rapor_service()->getGecenAyKarsilastirma($baslangic, $bitis);
    }
}

/**
 * Günlük satış verileri (grafik için)
 * @deprecated rapor_service()->getGunlukSatislar() kullanın
 */
if (!function_exists('getGunlukSatislar')) {
    function getGunlukSatislar($baslangic, $bitis) {
        return rapor_service()->getGunlukSatislar($baslangic, $bitis);
    }
}

/**
 * Kategori dağılımı
 * @deprecated rapor_service()->getKategoriDagilimi() kullanın
 */
if (!function_exists('getKategoriDagilimi')) {
    function getKategoriDagilimi($baslangic, $bitis) {
        return rapor_service()->getKategoriDagilimi($baslangic, $bitis);
    }
}

/**
 * En çok satan kategoriler
 * @deprecated rapor_service()->getEnCokSatanlar() kullanın
 */
if (!function_exists('getEnCokSatanlar')) {
    function getEnCokSatanlar($baslangic, $bitis, $limit = 5) {
        return rapor_service()->getEnCokSatanlar($baslangic, $bitis, $limit);
    }
}

/**
 * Müşteri analizi
 * @deprecated rapor_service()->getMusteriAnalizi() kullanın
 */
if (!function_exists('getMusteriAnalizi')) {
    function getMusteriAnalizi($baslangic, $bitis) {
        return rapor_service()->getMusteriAnalizi($baslangic, $bitis);
    }
}

/**
 * Ödeme tipi dağılımı
 * @deprecated rapor_service()->getOdemeTipiDagilimi() kullanın
 */
if (!function_exists('getOdemeTipiDagilimi')) {
    function getOdemeTipiDagilimi($baslangic, $bitis) {
        return rapor_service()->getOdemeTipiDagilimi($baslangic, $bitis);
    }
}

/**
 * Kanal dağılımı
 * @deprecated rapor_service()->getKanalDagilimi() kullanın
 */
if (!function_exists('getKanalDagilimi')) {
    function getKanalDagilimi($baslangic, $bitis) {
        return rapor_service()->getKanalDagilimi($baslangic, $bitis);
    }
}

/**
 * Haftalık karşılaştırma (son 4 hafta)
 * @deprecated rapor_service()->getHaftalikKarsilastirma() kullanın
 */
if (!function_exists('getHaftalikKarsilastirma')) {
    function getHaftalikKarsilastirma() {
        return rapor_service()->getHaftalikKarsilastirma();
    }
}