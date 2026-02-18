<?php
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
 */
if (!function_exists('getYogunlukDurumu')) {
    function getYogunlukDurumu($puan) {
        if ($puan <= 10) {
            return ['class' => 'bos', 'label' => 'Boş'];
        } elseif ($puan <= 40) {
            return ['class' => 'uygun', 'label' => 'Uygun'];
        } elseif ($puan <= 80) {
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
    function formatPrice($price) {
        return number_format($price, 2, ',', '.') . ' ₺';
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
            return db()->fetchAll("SELECT * FROM kategoriler ORDER BY sira ASC, ad ASC");
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
            $sql = "SELECT u.*, k.ad as kategori_ad, k.slug as kategori_slug
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