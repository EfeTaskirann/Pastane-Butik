<?php
/**
 * File Upload Configuration
 *
 * @package Pastane
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage',
        ],

        'public' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../uploads',
            'url' => env('APP_URL') . '/uploads',
            'visibility' => 'public',
        ],

        'products' => [
            'driver' => 'local',
            'root' => __DIR__ . '/../uploads/products',
            'url' => env('APP_URL') . '/uploads/products',
            'visibility' => 'public',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_file_size' => (int) env('UPLOAD_MAX_SIZE', 5242880), // 5MB
        'max_files' => 10,
        'chunk_size' => 1048576, // 1MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    */
    'types' => [
        'images' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            'max_size' => 5242880, // 5MB
        ],

        'documents' => [
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'max_size' => 10485760, // 10MB
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    */
    'images' => [
        'driver' => 'gd', // gd or imagick
        'quality' => 85,
        'max_width' => 2000,
        'max_height' => 2000,

        'thumbnails' => [
            'small' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 400, 'height' => 400],
            'large' => ['width' => 800, 'height' => 800],
        ],

        'webp_conversion' => true,
    ],
];
