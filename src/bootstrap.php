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

// Use our own writable session directory instead of the server's default save
// path — on shared hosting the default path is sometimes not writable by PHP,
// which silently drops every session and causes an endless login redirect loop.
$sessionPath = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
session_save_path($sessionPath);

// A reverse proxy (Cloudflare, a load balancer) can terminate HTTPS itself, so
// the server's own HTTPS var isn't always set even for a genuinely HTTPS
// request — X-Forwarded-Proto covers that case too.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name(config('app.session_name') ?? 'depositfinance_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $isHttps,
]);
session_start();

use DepositFinance\Auth;

Auth::bootstrap();
