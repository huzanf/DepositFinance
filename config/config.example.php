<?php

// Copy this file to config.php (same directory) and fill in your own values.
// config.php is gitignored — never commit real credentials.

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'depositfinance',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    // The only email allowed to log in. OTP codes are only ever issued to this address.
    'owner_email' => 'you@example.com',

    'mail' => [
        // 'log'  -> OTP codes are written to storage/otp.log instead of being emailed.
        //           Use this while developing locally, before SMTP is configured.
        // 'smtp' -> OTP codes are sent by email over SMTP using the settings below.
        'driver' => 'log',

        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls', // 'tls' (STARTTLS) or 'ssl' (implicit TLS)
        'from_email' => 'noreply@example.com',
        'from_name' => 'DepositFinance',
    ],

    'app' => [
        'timezone' => 'Asia/Kolkata',
        'session_name' => 'depositfinance_session',
    ],
];
