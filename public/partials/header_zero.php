<?php
/** @var string $activeNav */
$activeNav = $activeNav ?? '';
$isAdmin = \DepositFinance\Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' · DepositFinance' : 'DepositFinance' ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/theme-zero.css') ?>">
</head>
<body>
<?php if (\DepositFinance\Auth::isLoggedIn()): ?>
<header class="topbar">
    <div class="container">
        <div class="brand">DepositFinance</div>
        <nav class="mainnav">
            <a href="<?= base_url('index.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= base_url('instruments.php') ?>" class="<?= $activeNav === 'instruments' ? 'active' : '' ?>">Instruments</a>
            <a href="<?= base_url('members.php') ?>" class="<?= $activeNav === 'members' ? 'active' : '' ?>">Members</a>
            <a href="<?= base_url('goals.php') ?>" class="<?= $activeNav === 'goals' ? 'active' : '' ?>">Goals</a>
            <a href="<?= base_url('calendar.php') ?>" class="<?= $activeNav === 'calendar' ? 'active' : '' ?>">Calendar</a>
            <a href="<?= base_url('reports.php') ?>" class="<?= $activeNav === 'reports' ? 'active' : '' ?>">Reports</a>
            <?php if ($isAdmin): ?>
                <a href="<?= base_url('users.php') ?>" class="<?= $activeNav === 'users' ? 'active' : '' ?>">Users</a>
                <a href="<?= base_url('login_audit.php') ?>" class="<?= $activeNav === 'login_audit' ? 'active' : '' ?>">Login Activity</a>
            <?php endif; ?>
        </nav>
        <div class="topbar-right">
            <span><?= e($_SESSION['user_name'] ?? '') ?></span>
            <form method="post" action="<?= base_url('logout.php') ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Log out</button>
            </form>
        </div>
    </div>
</header>
<script>
(function () {
    // Keeps the server-side idle clock in sync while this tab stays open and
    // in use, so an idle timeout only hits someone who's actually stepped away.
    var PING_INTERVAL_MS = 5 * 60 * 1000;
    setInterval(function () {
        fetch('<?= base_url('session_ping.php') ?>', { credentials: 'same-origin' }).catch(function () {});
    }, PING_INTERVAL_MS);
})();
</script>
<?php endif; ?>
<div class="container">
    <?php $flashSuccess = flash('success'); $flashError = flash('error'); ?>
    <?php if ($flashSuccess): ?><div class="flash flash-success"><?= e($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="flash flash-error"><?= e($flashError) ?></div><?php endif; ?>
