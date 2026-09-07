<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    Auth::logout();
}

redirect('/login.php');
