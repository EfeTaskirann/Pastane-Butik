<?php
/**
 * Logging Configuration
 *
 * @package Pastane
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    */
    'default' => env('LOG_CHANNEL', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../' . env('LOG_PATH', 'storage/logs') . '/app.log',
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'permission' => 0664,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => __DIR__ . '/../' . env('LOG_PATH', 'storage/logs') . '/app.log',
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'error' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../' . env('LOG_PATH', 'storage/logs') . '/error.log',
            'level' => 'error',
            'days' => 30,
        ],

        'security' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../' . env('LOG_PATH', 'storage/logs') . '/security.log',
            'level' => 'info',
            'days' => 90,
        ],

        'audit' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../' . env('LOG_PATH', 'storage/logs') . '/audit.log',
            'level' => 'info',
            'days' => 365,
        ],

        'api' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../' . env('LOG_PATH', 'storage/logs') . '/api.log',
            'level' => 'info',
            'days' => 14,
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Levels
    |--------------------------------------------------------------------------
    | emergency, alert, critical, error, warning, notice, info, debug
    */
    'levels' => [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Format
    |--------------------------------------------------------------------------
    */
    'format' => [
        'date' => 'Y-m-d H:i:s',
        'timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),
        'include_trace' => env('APP_DEBUG', false),
        'json' => false, // Set to true for structured logging
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Masking
    |--------------------------------------------------------------------------
    */
    'mask' => [
        'fields' => [
            'password',
            'sifre',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'cvv',
        ],
        'replacement' => '********',
    ],
];
