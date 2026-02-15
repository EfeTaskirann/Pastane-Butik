<?php

declare(strict_types=1);

namespace Pastane\Middleware;

/**
 * Middleware Interface
 *
 * Tüm middleware'ler için interface.
 *
 * @package Pastane\Middleware
 * @since 1.0.0
 */
interface MiddlewareInterface
{
    /**
     * Handle the request
     *
     * @param callable $next Next middleware
     * @return mixed
     */
    public function handle(callable $next): mixed;
}
