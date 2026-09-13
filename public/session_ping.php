<?php

/**
 * Heartbeat an open tab calls periodically (see partials/header.php) so the
 * server-side idle clock (Auth::check()) stays in sync with someone actively
 * viewing a single page, not just navigating between pages.
 */
require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);
