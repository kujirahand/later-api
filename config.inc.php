<?php
/**
 * Later API Active Configuration
 */

declare(strict_types=1);

return [
    // Database configuration
    'db' => [
        'users_db' => __DIR__ . '/data/users.db',
        'users_dir' => __DIR__ . '/data/users',
    ],

    // Mail configurations
    'mail' => [
        // 'log' for writing to local log file (data/mail_debug.log) and showing on screen, 
        // 'mail' for PHP's built-in mail()
        'driver' => 'log', 
        
        // SMTP settings
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',

        // Sender information
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Later API',
    ],

    // Session security
    'session' => [
        'cookie_secure' => false,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],

    'debug' => true,
];
