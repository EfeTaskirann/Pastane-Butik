<?php
/**
 * PHPUnit Bootstrap
 *
 * Test ortamını hazırlar.
 *
 * @package Pastane\Tests
 */

declare(strict_types=1);

// Set test environment
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load Composer autoloader if exists
$autoloadPath = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Load test environment file if exists
$testEnvFile = BASE_PATH . '/.env.testing';
if (file_exists($testEnvFile)) {
    $lines = file($testEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

// Load bootstrap (will use testing environment)
require_once BASE_PATH . '/includes/bootstrap.php';
