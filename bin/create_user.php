<?php

/**
 * Create (or reactivate) a portal login from the command line — mainly for
 * bootstrapping the very first admin, since the Users page itself requires
 * being logged in as an admin already:
 *
 *   php bin/create_user.php "Jane Doe" jane@example.org admin
 *
 * There's no password to set — login is email + a one-time code sent to that
 * address. Role is optional and defaults to "member"; pass "admin" for full
 * access including the Users and Login Activity pages.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require __DIR__ . '/../src/helpers.php';

spl_autoload_register(function (string $class) {
    $prefix = 'DepositFinance\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

use DepositFinance\User;

[$script, $name, $email, $role] = array_pad($argv, 4, null);

if (!$name || !$email) {
    fwrite(STDERR, "Usage: php bin/create_user.php \"Full Name\" email@example.com [admin|member]\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "That doesn't look like a valid email address: {$email}\n");
    exit(1);
}

$role = in_array($role, User::ROLES, true) ? $role : 'member';

User::createOrUpdate($name, $email, $role);

echo "User \"{$name}\" ({$role}) saved. They can log in with {$email} — no password needed.\n";
