<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Mailer;

if (Auth::isLoggedIn()) {
    redirect('/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (!Auth::isOwnerEmail($email)) {
        // Don't reveal whether the address matches the owner account.
        flash('success', "If {$email} is authorized, a login code has been sent.");
        redirect('/verify.php?email=' . urlencode($email));
    } else {
        $code = Auth::issueOtp(Auth::ownerEmail());

        if ($code === null) {
            $error = 'A code was already sent recently. Please wait a minute and try again, or check your email.';
        } else {
            Mailer::sendOtp(Auth::ownerEmail(), $code);
            flash('success', "A login code has been sent to {$email}.");
            redirect('/verify.php?email=' . urlencode($email));
        }
    }
}

$pageTitle = 'Log in';
$activeNav = '';
require __DIR__ . '/partials/header.php';
?>

<div class="auth-wrap">
    <h1>DepositFinance</h1>
    <div class="card">
        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <p class="muted" style="margin-top:0">Enter your email to receive a one-time login code.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-row">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Send login code</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
