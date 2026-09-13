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
    } elseif (!Auth::emailExists($email)) {
        $error = 'No DepositFinance account found for that email address.';
    } else {
        $code = Auth::issueOtp($email);

        if ($code === null) {
            $error = 'A code was already sent recently. Please wait a minute and try again, or check your email.';
        } else {
            Mailer::sendOtp($email, $code);
            $_SESSION['pending_otp_email'] = $email;
            flash('success', "A login code has been sent to {$email}.");
            redirect('/verify.php');
        }
    }
}

$theme = current_theme();
$pageTitle = 'Log in';
$activeNav = '';
require __DIR__ . '/partials/header.php';
?>

<?php if ($theme === 'zero'): ?>
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
<?php else: ?>
    <div class="card">
        <div class="auth-icon"><?= icon('mail', 24) ?></div>
        <h1>Welcome back</h1>
        <p class="auth-sub">Enter your email to receive a one-time login code.</p>
        <?php if ($error): ?><div class="flash flash-error"><?= icon('x', 16) ?><span><?= e($error) ?></span></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-row">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" required autofocus
                       placeholder="you@example.com"
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-block">Send login code</button>
            </div>
        </form>
    </div>
    <p class="auth-footnote">Secure. Simple. Always you.</p>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
