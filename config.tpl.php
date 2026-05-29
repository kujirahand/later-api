<?php
/**
 * Later API Configuration Template
 * Copy this file to config.inc.php and configure your settings.
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
        // 'log' for writing to local log file (data/mail_debug.log), 
        // 'mail' for PHP's built-in mail(), 
        // 'smtp' for custom SMTP (needs standard PHP SMTP or we can implement a basic SMTP client, or mail() fallback)
        'driver' => 'log', 
        
        // SMTP settings (used if driver is smtp)
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_secure' => 'tls', // 'ssl', 'tls', or null
        'smtp_username' => 'your-username@example.com',
        'smtp_password' => 'your-smtp-password',

        // Sender information
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Later API',
    ],

    // Session security
    'session' => [
        'cookie_secure' => false, // Set to true if using HTTPS
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],

    // Developer / debug options
    'debug' => true, // Set to false in production
];
