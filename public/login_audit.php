<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Database;

Auth::requireAdmin();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 75;
$offset = ($page - 1) * $perPage;

$pdo = Database::connection();
$total = (int) $pdo->query('SELECT COUNT(*) FROM login_audit')->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT la.*, u.name AS user_name
     FROM login_audit la
     LEFT JOIN users u ON u.id = la.user_id
     ORDER BY la.id DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
);
$stmt->execute();
$rows = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $perPage));

$eventLabels = [
    'otp_requested' => ['Code sent', 'active'],
    'otp_rate_limited' => ['Code request throttled', 'matured'],
    'otp_verified' => ['Logged in', 'active'],
    'otp_failed' => ['Wrong code', 'closed'],
    'otp_expired' => ['Code expired', 'matured'],
    'session_replaced' => ['Session ended (new login elsewhere)', 'matured'],
    'session_timeout' => ['Session ended (idle timeout)', 'matured'],
    'logout' => ['Logged out', 'active'],
];

$pageTitle = 'Login Activity';
$activeNav = 'login_audit';
require __DIR__ . '/partials/header.php';
?>

<h1>Login Activity</h1>
<p class="muted" style="margin-top:-10px">Every login code request, verification, session takeover, and logout — newest first.</p>

<div class="card" style="padding:0">
    <table>
        <thead>
        <tr>
            <th>When</th>
            <th>User</th>
            <th>Email</th>
            <th>Event</th>
            <th>IP address</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $label = $eventLabels[$row['event']] ?? [$row['event'], 'matured']; ?>
            <tr>
                <td class="muted"><?= e(date('d M Y, H:i:s', strtotime($row['created_at']))) ?></td>
                <td><?= e($row['user_name'] ?: '—') ?></td>
                <td class="muted"><?= e($row['email'] ?? '') ?></td>
                <td><span class="badge badge-<?= $label[1] ?>"><?= e($label[0]) ?></span></td>
                <td class="muted"><?= e($row['ip_address'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="muted" style="padding:20px">No login activity yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="filters">
    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm">&larr; Prev</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($total) ?> total)</span>
    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm">Next &rarr;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
