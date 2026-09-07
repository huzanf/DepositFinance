<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Member;

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$member = $id ? Member::find($id) : null;

if ($id && !$member) {
    flash('error', 'Member not found.');
    redirect('/members.php');
}

$errors = [];
$form = $member ?? ['name' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    }

    if (empty($errors)) {
        if ($member) {
            Member::update($id, $form);
            flash('success', 'Member updated.');
        } else {
            Member::create($form);
            flash('success', 'Member added.');
        }
        redirect('/members.php');
    }
}

$pageTitle = $member ? 'Edit member' : 'Add member';
$activeNav = 'members';
require __DIR__ . '/partials/header.php';
?>

<h1><?= $member ? 'Edit member' : 'Add member' ?></h1>

<?php if (!empty($errors)): ?>
    <div class="flash flash-error">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required value="<?= e($form['name']) ?>">
        </div>
        <div class="form-row">
            <label for="notes">Notes</label>
            <input type="text" id="notes" name="notes" placeholder="Optional" value="<?= e($form['notes'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?= $member ? 'Save changes' : 'Add member' ?></button>
            <a href="<?= base_url('members.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
