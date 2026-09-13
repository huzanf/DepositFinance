<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\User;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'member';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a name and a valid email address.');
        } else {
            User::createOrUpdate($name, $email, $role);
            flash('success', "\"{$name}\" ({$role}) saved. They can log in with {$email} now.");
        }
        redirect('/users.php');
    }

    if ($action === 'toggle_active') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            flash('error', "You can't deactivate your own account.");
        } else {
            User::toggleActive($userId);
            flash('success', 'Updated.');
        }
        redirect('/users.php');
    }
}

$users = User::all();

$pageTitle = 'Users';
$activeNav = 'users';
require __DIR__ . '/partials/header.php';
?>

<h1>Users</h1>
<p class="muted" style="margin-top:-10px">Login is email + a one-time code — there's no password to set. Saving an email that already exists updates that person instead of creating a duplicate.</p>

<div class="card">
    <h2>Add or update a user</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="grid grid-2">
            <div class="form-row">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
        </div>
        <div class="form-row">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="member">Member — full access to the portal</option>
                <option value="admin">Admin — full access, plus managing users</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Save user</button>
        </div>
    </form>
</div>

<div class="card" style="padding:0">
    <table>
        <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last login</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e(ucfirst($u['role'])) ?></td>
                <td>
                    <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-closed' ?>">
                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="muted"><?= $u['last_login_at'] ? e(date('d M Y, H:i', strtotime($u['last_login_at']))) : '—' ?></td>
                <td>
                    <?php if ((int) $u['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                        <form method="post" class="inline" onsubmit="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?> <?= e($u['name']) ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:12px">(you)</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
