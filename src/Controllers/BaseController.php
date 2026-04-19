<?php

declare(strict_types=1);

namespace Pastane\Controllers;

use Pastane\Exceptions\HttpException;

/**
 * Base Controller
 *
 * Tüm controller'lar için temel sınıf.
 * Request/Response yönetimi, validation, JSON response helpers.
 *
 * @package Pastane\Controllers
 * @since 1.0.0
 */
abstract class BaseController
{
    /**
     * @var array Request data
     */
    protected array $request = [];

    /**
     * @var array Query parameters
     */
    protected array $query = [];

    /**
     * @var array Route parameters
     */
    protected array $params = [];

    /**
     * @var array Current user (if authenticated)
     */
    protected ?array $user = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->request = $this->parseRequest();
        $this->query = $_GET;
    }

    /**
     * Parse request body
     *
     * @return array
     */
    protected function parseRequest(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }

        return array_merge($_GET, $_POST);
    }

    /**
     * Get request input
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return $this->request[$key] ?? $default;
    }

    /**
     * Get query parameter
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Set route parameters
     *
     * @param array $params
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get route parameter
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Set authenticated user
     *
     * @param array|null $user
     * @return void
     */
    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    /**
     * Send JSON response
     *
     * @param mixed $data
     * @param int $status
     * @return never
     */
    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send success response
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $status
     * @return never
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): never
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->json($response, $status);
    }

    /**
     * Send error response
     *
     * @param string $message
     * @param int $status
     * @param array|null $errors
     * @return never
     */
    protected function error(string $message, int $status = 400, ?array $errors = null): never
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    /**
     * Send created response
     *
     * @param mixed $data
     * @param string|null $message
     * @return never
     */
    protected function created(mixed $data = null, ?string $message = null): never
    {
        $this->success($data, $message ?? 'Başarıyla oluşturuldu.', 201);
    }

    /**
     * Send no content response
     *
     * @return never
     */
    protected function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    /**
     * Send not found response
     *
     * @param string $message
     * @return never
     */
    protected function notFound(string $message = 'Kayıt bulunamadı.'): never
    {
        $this->error($message, 404);
    }

    /**
     * Send unauthorized response
     *
     * @param string $message
     * @return never
     */
    protected function unauthorized(string $message = 'Yetkilendirme gerekli.'): never
    {
        $this->error($message, 401);
    }

    /**
     * Send forbidden response
     *
     * @param string $message
     * @return never
     */
    protected function forbidden(string $message = 'Bu işlem için yetkiniz yok.'): never
    {
        $this->error($message, 403);
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     * @param int $status
     * @return never
     */
    protected function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    /**
     * Render view (for traditional pages)
     *
     * @param string $view View path (relative to views/, dot-notation: admin.urunler.index)
     * @param array $data Data to pass to view
     * @return void
     */
    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = dirname(__DIR__, 2) . '/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new HttpException("View not found: {$view}", 500);
        }

        include $viewPath;
    }

    /**
     * View render (admin sayfaları için alias, Laravel konvansiyonuna yakın).
     *
     * Admin sayfaları header.php/footer.php ile sarmalanır. Controller action'ları
     * `$this->view('admin.urunler.index', [...])` çağırır; view dosyasının sorumluluğu
     * yalnızca HTML + `<?= ?>` echo'ları olmalıdır (logic yasak).
     *
     * @param string $view dot-notation (admin.urunler.index)
     * @param array $data view'e taşınacak değişkenler
     * @return void
     */
    protected function view(string $view, array $data = []): void
    {
        $this->render($view, $data);
    }

    /**
     * Admin partial include — view içinden çağrılır, aynı $data scope'unu paylaşır.
     *
     * @param string $view dot-notation (admin.urunler._row)
     * @param array $data ek değişkenler (opsiyonel)
     * @return void
     */
    protected function partial(string $view, array $data = []): void
    {
        $this->render($view, $data);
    }

    /**
     * Flash mesajı kuyruğa al (session tabanlı, tek seferlik).
     * helpers.php içindeki setFlash() wrapper'ı — controller API'si tutarlı kalsın diye.
     *
     * @param string $type success|error|warning|info
     * @param string $message
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        if (function_exists('setFlash')) {
            setFlash($type, $message);
            return;
        }
        // Fallback — helpers yüklenmemişse session'a doğrudan yaz
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * CSRF token doğrula; başarısızsa flash + redirect.
     * Admin POST action'larının başında çağrılmalı.
     *
     * @param string $redirectOnFail başarısız olursa yönlendirilecek relative URL
     * @return void
     */
    protected function requireCsrf(string $redirectOnFail): void
    {
        $valid = false;

        if (function_exists('verifyCSRF')) {
            $valid = verifyCSRF();
        } elseif (function_exists('validateSecureCSRFToken')) {
            $token = $_POST['csrf_token'] ?? '';
            $valid = validateSecureCSRFToken($token);
        }

        if (!$valid) {
            $this->flash('error', 'Güvenlik doğrulaması başarısız.');
            $this->redirect($redirectOnFail);
        }
    }

    /**
     * Permission guard — helpers.php require_permission() wrapper'ı.
     * Yetki yoksa 403 sayfası render edilir ve script sonlanır.
     *
     * @param string $permission (ör. "product.view")
     * @return void
     */
    protected function requirePermission(string $permission): void
    {
        if (function_exists('require_permission')) {
            require_permission($permission);
        }
    }

    /**
     * Get pagination parameters
     *
     * @param int $defaultLimit
     * @param int $maxLimit
     * @return array ['page' => int, 'limit' => int, 'offset' => int]
     */
    protected function pagination(int $defaultLimit = 20, int $maxLimit = 100): array
    {
        $page = max(1, (int)$this->query('page', 1));
        $limit = min($maxLimit, max(1, (int)$this->query('limit', $defaultLimit)));
        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Create paginated response
     *
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $limit
     * @return array
     */
    protected function paginated(array $items, int $total, int $page, int $limit): array
    {
        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ];
    }
}
