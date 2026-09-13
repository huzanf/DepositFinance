<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;

if (Auth::isLoggedIn()) {
    redirect('/index.php');
}

$email = trim((string) ($_SESSION['pending_otp_email'] ?? ''));

if ($email === '') {
    redirect('/login.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? 'verify';

    if ($action === 'start_over') {
        unset($_SESSION['pending_otp_email']);
        redirect('/login.php');
    }

    $code = trim((string) ($_POST['code'] ?? ''));
    $result = Auth::verifyOtp($email, $code);

    switch ($result) {
        case 'ok':
            unset($_SESSION['pending_otp_email']);
            redirect('/index.php');
            break;
        case 'expired':
            $error = 'That code has expired. Request a new one.';
            break;
        case 'too_many_attempts':
            $error = 'Too many incorrect attempts. Request a new code.';
            break;
        default:
            $error = 'Incorrect code. Please try again.';
    }
}

$pageTitle = 'Verify code';
$activeNav = '';
require __DIR__ . '/partials/header.php';
?>

<div class="auth-wrap">
    <h1>Enter your code</h1>
    <div class="card">
        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <p class="muted" style="margin-top:0">
            We sent a 6-digit code to <strong><?= e($email) ?></strong>. It expires in 10 minutes.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify">
            <div class="form-row">
                <label for="code">Login code</label>
                <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" required autofocus placeholder="123456">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Verify &amp; log in</button>
            </div>
        </form>
        <form method="post" style="margin-top:16px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="start_over">
            <button type="submit" class="btn btn-secondary btn-sm">Use a different email</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
