<?php

/**
 * Loads config/config.php once and caches it. Pass a dot-path (e.g. 'mail.driver')
 * to get a nested value, or a top-level key, or nothing for the whole array.
 */
function config(?string $key = null)
{
    static $config = null;

    if ($config === null) {
        $path = __DIR__ . '/../config/config.php';

        if (!file_exists($path)) {
            throw new RuntimeException(
                'config/config.php not found. Copy config/config.example.php to config/config.php and fill in your settings.'
            );
        }

        $config = require $path;
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }

    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}
