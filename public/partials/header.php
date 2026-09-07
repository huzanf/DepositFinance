<?php
/** @var string $activeNav */
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' · DepositFinance' : 'DepositFinance' ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/style.css') ?>">
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
        </nav>
        <div class="topbar-right">
            <span><?= e($_SESSION['user_email']) ?></span>
            <form method="post" action="<?= base_url('logout.php') ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Log out</button>
            </form>
        </div>
    </div>
</header>
<?php endif; ?>
<div class="container">
    <?php $success = flash('success'); $error = flash('error'); ?>
    <?php if ($success): ?><div class="flash flash-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
