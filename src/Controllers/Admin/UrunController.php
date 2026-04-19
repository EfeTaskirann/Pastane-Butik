<?php

declare(strict_types=1);

namespace Pastane\Controllers\Admin;

use Pastane\Controllers\BaseController;
use Pastane\Services\UrunService;

/**
 * Admin Urun Controller
 *
 * admin/urunler.php sayfasının business logic'i — listeleme, silme, aktif/pasif toggle.
 * Ürün create/update ayrı sayfada (urun-ekle.php, urun-duzenle.php) — bu controller
 * yalnızca liste action'larını yönetir; ileride oradaki mantık da buraya taşınacak.
 *
 * @package Pastane\Controllers\Admin
 * @since 1.0.0
 */
class UrunController extends BaseController
{
    /**
     * Liste sayfasının canonical URL'i — redirect hedefi.
     */
    private const LIST_URL = 'urunler.php';

    /**
     * @var UrunService
     */
    private UrunService $urunService;

    /**
     * Constructor — service dependency injection edilebilir (test için),
     * yoksa global helper ile resolve eder.
     *
     * @param UrunService|null $urunService
     */
    public function __construct(?UrunService $urunService = null)
    {
        parent::__construct();
        $this->urunService = $urunService ?? urun_service();
    }

    /**
     * Ürün listesi sayfası.
     * URL parametreleri:
     *   ?islem=silindi|guncellendi  → client-side toast için
     *
     * @return void
     */
    public function index(): void
    {
        $this->requirePermission('product.view');

        $products       = $this->urunService->getAllWithCategory();
        $allCategories  = function_exists('getCategories') ? getCategories() : [];

        $activeCount   = 0;
        foreach ($products as $p) {
            if ((int)($p['aktif'] ?? 0) === 1) {
                $activeCount++;
            }
        }
        $inactiveCount = count($products) - $activeCount;

        $toastIslem = $_GET['islem'] ?? null;
        $toastMessage = null;
        if ($toastIslem === 'silindi') {
            $toastMessage = 'Ürün başarıyla silindi.';
        } elseif ($toastIslem === 'guncellendi') {
            $toastMessage = 'Ürün durumu güncellendi.';
        }

        $this->view('admin.urunler.index', [
            'products'      => $products,
            'allCategories' => $allCategories,
            'activeCount'   => $activeCount,
            'inactiveCount' => $inactiveCount,
            'toastMessage'  => $toastMessage,
        ]);
    }

    /**
     * Yeni ürün oluşturma — ileri sprint'te `urun-ekle.php` buraya taşınacak.
     * Sprint 2: sadece iskelet, henüz `urun-ekle.php` ayrı dosya.
     *
     * @return void
     */
    public function store(): void
    {
        $this->requirePermission('product.create');
        $this->requireCsrf(self::LIST_URL);

        $data = [
            'isim'        => trim((string)($_POST['isim'] ?? '')),
            'fiyat'       => (float)($_POST['fiyat'] ?? 0),
            'kategori_id' => (int)($_POST['kategori_id'] ?? 0),
            'aciklama'    => trim((string)($_POST['aciklama'] ?? '')),
            'aktif'       => isset($_POST['aktif']) ? 1 : 0,
        ];

        try {
            $this->urunService->create($data);
            $this->flash('success', 'Ürün başarıyla eklendi.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Ürün eklenirken hata oluştu: ' . $e->getMessage());
        }

        $this->redirect(self::LIST_URL);
    }

    /**
     * Ürün güncelleme — ileri sprint'te `urun-duzenle.php` buraya taşınacak.
     *
     * @return void
     */
    public function update(): void
    {
        $this->requirePermission('product.update');
        $this->requireCsrf(self::LIST_URL);

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('error', 'Geçersiz ürün ID.');
            $this->redirect(self::LIST_URL);
        }

        $data = [
            'isim'        => trim((string)($_POST['isim'] ?? '')),
            'fiyat'       => (float)($_POST['fiyat'] ?? 0),
            'kategori_id' => (int)($_POST['kategori_id'] ?? 0),
            'aciklama'    => trim((string)($_POST['aciklama'] ?? '')),
            'aktif'       => isset($_POST['aktif']) ? 1 : 0,
        ];

        try {
            $this->urunService->update($id, $data);
            $this->flash('success', 'Ürün başarıyla güncellendi.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Ürün güncellenirken hata oluştu: ' . $e->getMessage());
        }

        $this->redirect(self::LIST_URL . '?islem=guncellendi');
    }

    /**
     * Ürün silme (CSRF + permission + görsel temizliği).
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->requirePermission('product.delete');
        $this->requireCsrf(self::LIST_URL);

        $id = (int)($_POST['delete_id'] ?? 0);
        if ($id <= 0) {
            $this->redirect(self::LIST_URL);
        }

        $product = $this->urunService->find($id);
        if (!$product) {
            $this->flash('error', 'Ürün bulunamadı.');
            $this->redirect(self::LIST_URL);
        }

        // Görseli sil (varsa)
        if (!empty($product['gorsel']) && function_exists('deleteImage')) {
            deleteImage($product['gorsel']);
        }

        try {
            $this->urunService->delete($id);
            $this->flash('success', 'Ürün başarıyla silindi.');
            $this->redirect(self::LIST_URL . '?islem=silindi');
        } catch (\Throwable $e) {
            $this->flash('error', 'Ürün silinirken hata oluştu: ' . $e->getMessage());
            $this->redirect(self::LIST_URL);
        }
    }

    /**
     * Ürün aktif/pasif toggle.
     *
     * @return void
     */
    public function toggleAktiflik(): void
    {
        $this->requirePermission('product.update');
        $this->requireCsrf(self::LIST_URL);

        $id = (int)($_POST['toggle_id'] ?? 0);
        if ($id <= 0) {
            $this->redirect(self::LIST_URL);
        }

        try {
            $this->urunService->toggleActive($id);
            $this->flash('success', 'Ürün durumu güncellendi.');
            $this->redirect(self::LIST_URL . '?islem=guncellendi');
        } catch (\Throwable $e) {
            $this->flash('error', 'Durum güncellenemedi: ' . $e->getMessage());
            $this->redirect(self::LIST_URL);
        }
    }

    /**
     * Router entry point — admin/urunler.php'den çağrılır.
     * GET → index, POST → action dispatch (delete_id / toggle_id / action).
     *
     * @return void
     */
    public function handle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            // Öncelik sırası: açık "action" parametresi → delete_id → toggle_id
            $action = $_POST['action'] ?? null;

            if ($action === 'store') {
                $this->store();
                return;
            }
            if ($action === 'update') {
                $this->update();
                return;
            }
            if (!empty($_POST['delete_id'])) {
                $this->destroy();
                return;
            }
            if (!empty($_POST['toggle_id'])) {
                $this->toggleAktiflik();
                return;
            }
            // Tanımsız POST → listeye dön
            $this->redirect(self::LIST_URL);
        }

        $this->index();
    }
}
