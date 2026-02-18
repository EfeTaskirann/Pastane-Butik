<?php

declare(strict_types=1);

namespace Pastane\Router;

use Pastane\Exceptions\HttpException;

/**
 * Simple Router
 *
 * Basit ama etkili bir router implementasyonu.
 *
 * @package Pastane\Router
 * @since 1.0.0
 */
class Router
{
    /**
     * @var array Registered routes
     */
    protected array $routes = [];

    /**
     * @var array Global middleware
     */
    protected array $middleware = [];

    /**
     * @var array Named routes
     */
    protected array $namedRoutes = [];

    /**
     * @var string Current group prefix
     */
    protected string $groupPrefix = '';

    /**
     * @var array Current group middleware
     */
    protected array $groupMiddleware = [];

    /**
     * @var self|null Singleton instance
     */
    protected static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register GET route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function get(string $path, callable|array $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register POST route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function post(string $path, callable|array $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register PUT route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function put(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register PATCH route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function patch(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Register DELETE route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function delete(string $path, callable|array $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register route for multiple methods
     *
     * @param array $methods
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function match(array $methods, string $path, callable|array $handler): Route
    {
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $path, $handler);
        }
        return $route;
    }

    /**
     * Register route for all methods
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function any(string $path, callable|array $handler): Route
    {
        return $this->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler);
    }

    /**
     * Add route
     *
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    protected function addRoute(string $method, string $path, callable|array $handler): Route
    {
        $fullPath = $this->groupPrefix . $path;
        $route = new Route($method, $fullPath, $handler);
        $route->middleware($this->groupMiddleware);

        $this->routes[$method][$fullPath] = $route;

        return $route;
    }

    /**
     * Create route group
     *
     * @param array $attributes ['prefix' => '', 'middleware' => []]
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix .= $attributes['prefix'] ?? '';
        $this->groupMiddleware = array_merge(
            $this->groupMiddleware,
            (array)($attributes['middleware'] ?? [])
        );

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Add global middleware
     *
     * @param string|array $middleware
     * @return self
     */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array)$middleware);
        return $this;
    }

    /**
     * Register RESTful resource routes
     *
     * @param string $name Resource name
     * @param string $controller Controller class
     * @param array $options
     * @return void
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? ['index', 'show', 'store', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $actions = array_diff($only, $except);

        $routeMap = [
            'index' => ['GET', "/{$name}", 'index'],
            'show' => ['GET', "/{$name}/{id}", 'show'],
            'store' => ['POST', "/{$name}", 'store'],
            'update' => ['PUT', "/{$name}/{id}", 'update'],
            'destroy' => ['DELETE', "/{$name}/{id}", 'destroy'],
        ];

        foreach ($actions as $action) {
            if (isset($routeMap[$action])) {
                [$method, $path, $controllerMethod] = $routeMap[$action];
                $this->addRoute($method, $path, [$controller, $controllerMethod])
                    ->name("{$name}.{$action}");
            }
        }
    }

    /**
     * Dispatch the request
     *
     * @param string|null $method
     * @param string|null $uri
     * @return mixed
     * @throws HttpException
     */
    public function dispatch(?string $method = null, ?string $uri = null): mixed
    {
        $method = $method ?? $_SERVER['REQUEST_METHOD'];
        $uri = $uri ?? $this->getUri();

        // Find matching route
        $route = $this->findRoute($method, $uri);

        if ($route === null) {
            // Check if route exists for other methods (405 Method Not Allowed)
            $allowedMethods = $this->getAllowedMethods($uri);
            if (!empty($allowedMethods)) {
                header('Allow: ' . implode(', ', $allowedMethods));
                throw HttpException::methodNotAllowed(
                    'Bu endpoint için ' . $method . ' metodu desteklenmiyor. İzin verilen: ' . implode(', ', $allowedMethods)
                );
            }

            throw HttpException::notFound('Sayfa bulunamadı.');
        }

        // Execute middleware chain
        $middlewareStack = array_merge($this->middleware, $route->getMiddleware());

        return $this->executeMiddleware($middlewareStack, function () use ($route) {
            return $route->execute();
        });
    }

    /**
     * Get allowed HTTP methods for a given URI
     *
     * @param string $uri
     * @return array
     */
    protected function getAllowedMethods(string $uri): array
    {
        $allowed = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $path => $route) {
                if ($this->matchRoute($path, $uri) !== false) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        return $allowed;
    }

    /**
     * Find matching route
     *
     * @param string $method
     * @param string $uri
     * @return Route|null
     */
    protected function findRoute(string $method, string $uri): ?Route
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $path => $route) {
            $params = $this->matchRoute($path, $uri);

            if ($params !== false) {
                $route->setParams($params);
                return $route;
            }
        }

        return null;
    }

    /**
     * Match route pattern against URI
     *
     * @param string $pattern
     * @param string $uri
     * @return array|false
     */
    protected function matchRoute(string $pattern, string $uri): array|false
    {
        // Convert route parameters to regex
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = preg_replace('/\{([a-zA-Z_]+)\?\}/', '(?P<$1>[^/]*)?', $regex);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Execute middleware stack
     *
     * @param array $middleware
     * @param callable $core
     * @return mixed
     */
    protected function executeMiddleware(array $middleware, callable $core): mixed
    {
        if (empty($middleware)) {
            return $core();
        }

        $middlewareClass = array_shift($middleware);

        if (is_string($middlewareClass)) {
            $instance = new $middlewareClass();
        } else {
            $instance = $middlewareClass;
        }

        return $instance->handle(function () use ($middleware, $core) {
            return $this->executeMiddleware($middleware, $core);
        });
    }

    /**
     * Get current URI
     *
     * @return string
     */
    protected function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove base path if needed
        $basePath = config('app.base_path', '');
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        return '/' . trim($uri, '/');
    }

    /**
     * Generate URL for named route
     *
     * @param string $name
     * @param array $params
     * @return string
     */
    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $routes) {
            foreach ($routes as $route) {
                if ($route->getName() === $name) {
                    return $route->generateUrl($params);
                }
            }
        }

        return '/';
    }

    /**
     * Get all registered routes
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

/**
 * Route class
 */
class Route
{
    protected string $method;
    protected string $path;
    protected $handler;
    protected array $middleware = [];
    protected array $params = [];
    protected ?string $name = null;

    public function __construct(string $method, string $path, callable|array $handler)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
    }

    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array)$middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function execute(): mixed
    {
        if (is_callable($this->handler)) {
            return call_user_func($this->handler, $this->params);
        }

        if (is_array($this->handler)) {
            [$class, $method] = $this->handler;
            $controller = new $class();
            $controller->setParams($this->params);
            return $controller->$method();
        }

        throw new \RuntimeException('Invalid route handler');
    }

    public function generateUrl(array $params = []): string
    {
        $url = $this->path;

        foreach ($params as $key => $value) {
            $url = str_replace("{{$key}}", (string)$value, $url);
            $url = str_replace("{{$key}?}", (string)$value, $url);
        }

        // Remove unfilled optional params
        $url = preg_replace('/\{[a-zA-Z_]+\?\}/', '', $url);

        return $url;
    }
}
