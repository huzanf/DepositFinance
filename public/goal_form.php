<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Goal;

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$goal = $id ? Goal::find($id) : null;

if ($id && !$goal) {
    flash('error', 'Goal not found.');
    redirect('/goals.php');
}

$errors = [];
$form = $goal ?? ['name' => '', 'target_amount' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'target_amount' => trim((string) ($_POST['target_amount'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($form['target_amount'] !== '' && !is_numeric($form['target_amount'])) {
        $errors[] = 'Target amount must be a number.';
    }

    if (empty($errors)) {
        if ($goal) {
            Goal::update($id, $form);
            flash('success', 'Goal updated.');
        } else {
            Goal::create($form);
            flash('success', 'Goal added.');
        }
        redirect('/goals.php');
    }
}

$pageTitle = $goal ? 'Edit goal' : 'Add goal';
$activeNav = 'goals';
require __DIR__ . '/partials/header.php';
?>

<h1><?= $goal ? 'Edit goal' : 'Add goal' ?></h1>

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
            <input type="text" id="name" name="name" required placeholder="e.g. Travel, Emergency Fund"
                   value="<?= e($form['name']) ?>">
        </div>
        <div class="form-row">
            <label for="target_amount">Target amount</label>
            <input type="number" step="0.01" id="target_amount" name="target_amount" placeholder="Optional"
                   value="<?= e($form['target_amount'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label for="notes">Notes</label>
            <input type="text" id="notes" name="notes" placeholder="Optional" value="<?= e($form['notes'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?= $goal ? 'Save changes' : 'Add goal' ?></button>
            <a href="<?= base_url('goals.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
