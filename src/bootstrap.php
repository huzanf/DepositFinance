<?php

spl_autoload_register(function (string $class) {
    $prefix = 'DepositFinance\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($path)) {
        require $path;
    }
});

require_once __DIR__ . '/helpers.php';

date_default_timezone_set(config('app.timezone') ?? 'UTC');

session_name(config('app.session_name') ?? 'depositfinance_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

use DepositFinance\Auth;

Auth::bootstrap();
