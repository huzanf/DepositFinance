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

$theme = current_theme();
$pageTitle = 'Verify code';
$activeNav = '';
require __DIR__ . '/partials/header.php';
?>

<?php if ($theme === 'zero'): ?>
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
<?php else: ?>
    <div class="card">
        <div class="auth-icon"><?= icon('lock', 22) ?></div>
        <h1>Enter your code</h1>
        <p class="auth-sub">We sent a 6-digit code to <strong><?= e($email) ?></strong>. It expires in 10 minutes.</p>
        <?php if ($error): ?><div class="flash flash-error"><?= icon('x', 16) ?><span><?= e($error) ?></span></div><?php endif; ?>
        <form method="post" id="otp-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="code" id="otp-code-full">
            <div class="otp-boxes" id="otp-boxes">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" autocomplete="one-time-code" <?= $i === 0 ? 'autofocus' : '' ?>>
                <?php endfor; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-block">Verify &amp; log in</button>
            </div>
        </form>
        <form method="post" style="margin-top:14px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="start_over">
            <button type="submit" class="btn btn-ghost btn-block btn-sm">Use a different email</button>
        </form>
    </div>
    <script>
    (function () {
        var boxes = Array.prototype.slice.call(document.querySelectorAll('.otp-digit'));
        var fullField = document.getElementById('otp-code-full');
        var form = document.getElementById('otp-form');

        function sync() {
            fullField.value = boxes.map(function (b) { return b.value; }).join('');
        }

        boxes.forEach(function (box, i) {
            box.addEventListener('input', function () {
                box.value = box.value.replace(/[^0-9]/g, '').slice(-1);
                if (box.value && boxes[i + 1]) {
                    boxes[i + 1].focus();
                }
                sync();
            });

            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !box.value && boxes[i - 1]) {
                    boxes[i - 1].focus();
                }
            });

            box.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                if (!text) {
                    return;
                }
                e.preventDefault();
                text.slice(0, boxes.length).split('').forEach(function (digit, idx) {
                    if (boxes[idx]) {
                        boxes[idx].value = digit;
                    }
                });
                sync();
                var next = boxes[Math.min(text.length, boxes.length - 1)];
                if (next) {
                    next.focus();
                }
            });
        });

        form.addEventListener('submit', sync);
        sync();
    })();
    </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
