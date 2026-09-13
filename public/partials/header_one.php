<?php
/** @var string $activeNav */
$activeNav = $activeNav ?? '';
$loggedIn = \DepositFinance\Auth::isLoggedIn();
$isAdmin = $loggedIn && \DepositFinance\Auth::isAdmin();
$currentUserName = $_SESSION['user_name'] ?? '';
$avatarInitial = $currentUserName !== '' ? strtoupper(mb_substr($currentUserName, 0, 1)) : '?';
$hourNow = (int) date('G');
$greeting = $hourNow < 12 ? 'Good morning' : ($hourNow < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' · DepositFinance' : 'DepositFinance' ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/theme-one.css') ?>">
</head>
<body>
<?php if ($loggedIn): ?>
<div class="app-shell">
    <div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-mark"><?= icon('landmark', 16) ?></span>
            DepositFinance
        </div>
        <nav class="sidebar-nav">
            <a href="<?= base_url('index.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>"><?= icon('grid') ?> Dashboard</a>
            <a href="<?= base_url('instruments.php') ?>" class="<?= $activeNav === 'instruments' ? 'active' : '' ?>"><?= icon('layers') ?> Instruments</a>
            <a href="<?= base_url('members.php') ?>" class="<?= $activeNav === 'members' ? 'active' : '' ?>"><?= icon('users') ?> Members</a>
            <a href="<?= base_url('goals.php') ?>" class="<?= $activeNav === 'goals' ? 'active' : '' ?>"><?= icon('target') ?> Goals</a>
            <a href="<?= base_url('calendar.php') ?>" class="<?= $activeNav === 'calendar' ? 'active' : '' ?>"><?= icon('calendar') ?> Calendar</a>
            <a href="<?= base_url('reports.php') ?>" class="<?= $activeNav === 'reports' ? 'active' : '' ?>"><?= icon('bar-chart') ?> Reports</a>
            <?php if ($isAdmin): ?>
                <div class="sidebar-section-label">Admin</div>
                <a href="<?= base_url('users.php') ?>" class="<?= $activeNav === 'users' ? 'active' : '' ?>"><?= icon('shield') ?> Users</a>
                <a href="<?= base_url('login_audit.php') ?>" class="<?= $activeNav === 'login_audit' ? 'active' : '' ?>"><?= icon('activity') ?> Login Activity</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="tagline">Simple. Secure. Smarter.<br>Your family's wealth journey.</div>
        </div>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <div class="topbar-inner">
                <button type="button" class="hamburger" onclick="document.body.classList.add('sidebar-open')"><?= icon('menu', 22) ?></button>
                <form class="topbar-search" method="get" action="<?= base_url('instruments.php') ?>">
                    <?= icon('search', 16) ?>
                    <input type="text" name="q" placeholder="Search instruments…" value="<?= e($_GET['q'] ?? '') ?>">
                </form>
                <div class="topbar-right">
                    <a class="icon-btn" href="<?= base_url('calendar.php') ?>" title="Upcoming maturities"><?= icon('bell', 17) ?></a>
                    <div class="user-menu">
                        <button type="button" class="user-menu-btn" onclick="this.parentElement.querySelector('.user-dropdown').classList.toggle('open')">
                            <span class="avatar"><?= e($avatarInitial) ?></span>
                            <span><?= e($currentUserName) ?></span>
                            <?= icon('chevron-down', 14) ?>
                        </button>
                        <div class="user-dropdown">
                            <div class="dropdown-email"><?= e($_SESSION['user_email'] ?? $currentUserName) ?></div>
                            <form method="post" action="<?= base_url('logout.php') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-ghost btn-sm"><?= icon('log-out', 15) ?> Log out</button>
                            </form>
                        </div>
                    </div>
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

            document.addEventListener('click', function (e) {
                var menu = document.querySelector('.user-dropdown.open');
                if (menu && !e.target.closest('.user-menu')) {
                    menu.classList.remove('open');
                }
            });
        })();
        </script>
        <div class="content">
        <div class="container">
            <?php $flashSuccess = flash('success'); $flashError = flash('error'); ?>
            <?php if ($flashSuccess): ?><div class="flash flash-success"><?= icon('check', 16) ?><span><?= e($flashSuccess) ?></span></div><?php endif; ?>
            <?php if ($flashError): ?><div class="flash flash-error"><?= icon('x', 16) ?><span><?= e($flashError) ?></span></div><?php endif; ?>
<?php else: ?>
<div class="auth-shell">
    <div class="auth-wrap">
        <div class="auth-logo">
            <span class="brand-mark"><?= icon('landmark', 18) ?></span>
            DepositFinance
        </div>
            <?php $flashSuccess = flash('success'); $flashError = flash('error'); ?>
            <?php if ($flashSuccess): ?><div class="flash flash-success"><?= icon('check', 16) ?><span><?= e($flashSuccess) ?></span></div><?php endif; ?>
            <?php if ($flashError): ?><div class="flash flash-error"><?= icon('x', 16) ?><span><?= e($flashError) ?></span></div><?php endif; ?>
<?php endif; ?>
