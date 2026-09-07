<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Instrument;

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$instrument = $id ? Instrument::find($id) : null;

if ($id && !$instrument) {
    flash('error', 'Instrument not found.');
    redirect('/instruments.php');
}

$errors = [];
$form = $instrument ?? [
    'type' => 'FD',
    'name' => '',
    'institution' => '',
    'start_date' => date('Y-m-d'),
    'maturity_date' => '',
    'interest_rate' => '',
    'compounding_frequency' => 'quarterly',
    'principal_amount' => '',
    'installment_amount' => '',
    'frequency' => 'monthly',
    'status' => 'active',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form = [
        'type' => $_POST['type'] ?? '',
        'name' => trim((string) ($_POST['name'] ?? '')),
        'institution' => trim((string) ($_POST['institution'] ?? '')),
        'start_date' => $_POST['start_date'] ?? '',
        'maturity_date' => $_POST['maturity_date'] ?? '',
        'interest_rate' => trim((string) ($_POST['interest_rate'] ?? '')),
        'compounding_frequency' => $_POST['compounding_frequency'] ?? '',
        'principal_amount' => trim((string) ($_POST['principal_amount'] ?? '')),
        'installment_amount' => trim((string) ($_POST['installment_amount'] ?? '')),
        'frequency' => $_POST['frequency'] ?? 'monthly',
        'status' => $_POST['status'] ?? 'active',
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    if (!in_array($form['type'], Instrument::TYPES, true)) {
        $errors[] = 'Choose a valid instrument type.';
    }
    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($form['institution'] === '') {
        $errors[] = 'Institution is required.';
    }
    if (!$form['start_date'] || !strtotime($form['start_date'])) {
        $errors[] = 'A valid start date is required.';
    }
    if ($form['maturity_date'] !== '' && !strtotime($form['maturity_date'])) {
        $errors[] = 'Maturity date is not a valid date.';
    }
    if ($form['interest_rate'] !== '' && !is_numeric($form['interest_rate'])) {
        $errors[] = 'Interest rate must be a number.';
    }
    if ($form['principal_amount'] !== '' && !is_numeric($form['principal_amount'])) {
        $errors[] = 'Principal amount must be a number.';
    }
    if ($form['installment_amount'] !== '' && !is_numeric($form['installment_amount'])) {
        $errors[] = 'Installment amount must be a number.';
    }
    if (!in_array($form['frequency'], Instrument::FREQUENCIES, true)) {
        $errors[] = 'Choose a valid frequency.';
    }
    if (!in_array($form['status'], Instrument::STATUSES, true)) {
        $errors[] = 'Choose a valid status.';
    }
    if ($form['compounding_frequency'] !== '' && !in_array($form['compounding_frequency'], Instrument::COMPOUNDING, true)) {
        $errors[] = 'Choose a valid compounding frequency.';
    }

    if (empty($errors)) {
        if ($instrument) {
            Instrument::update($id, $form);
            flash('success', 'Instrument updated.');
            redirect('/instrument.php?id=' . $id);
        } else {
            $newId = Instrument::create($form);
            flash('success', 'Instrument added.');
            redirect('/instrument.php?id=' . $newId);
        }
    }
}

$pageTitle = $instrument ? 'Edit instrument' : 'Add investment';
$activeNav = 'instruments';
require __DIR__ . '/partials/header.php';
?>

<h1><?= $instrument ? 'Edit instrument' : 'Add investment' ?></h1>

<?php if (!empty($errors)): ?>
    <div class="flash flash-error">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="type">Type</label>
                <select id="type" name="type" required>
                    <?php foreach (Instrument::TYPES as $type): ?>
                        <option value="<?= $type ?>" <?= $form['type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <?php foreach (Instrument::STATUSES as $status): ?>
                        <option value="<?= $status ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required placeholder="e.g. HDFC 3-year FD"
                       value="<?= e($form['name']) ?>">
            </div>
            <div class="form-row">
                <label for="institution">Institution</label>
                <input type="text" id="institution" name="institution" required placeholder="e.g. HDFC Bank"
                       value="<?= e($form['institution']) ?>">
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="start_date">Start date</label>
                <input type="date" id="start_date" name="start_date" required value="<?= e($form['start_date']) ?>">
            </div>
            <div class="form-row">
                <label for="maturity_date">Maturity date</label>
                <input type="date" id="maturity_date" name="maturity_date" value="<?= e($form['maturity_date'] ?? '') ?>">
                <div class="hint">Leave blank for an open-ended SIP.</div>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="principal_amount">Principal / lump sum</label>
                <input type="number" step="0.01" id="principal_amount" name="principal_amount"
                       placeholder="For FDs" value="<?= e($form['principal_amount'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="installment_amount">Recurring installment amount</label>
                <input type="number" step="0.01" id="installment_amount" name="installment_amount"
                       placeholder="For RD / SIP" value="<?= e($form['installment_amount'] ?? '') ?>">
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="frequency">Contribution frequency</label>
                <select id="frequency" name="frequency">
                    <?php foreach (Instrument::FREQUENCIES as $freq): ?>
                        <option value="<?= $freq ?>" <?= $form['frequency'] === $freq ? 'selected' : '' ?>><?= ucfirst($freq) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="interest_rate">Interest rate / expected return (% p.a.)</label>
                <input type="number" step="0.01" id="interest_rate" name="interest_rate"
                       placeholder="Optional" value="<?= e($form['interest_rate'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <label for="compounding_frequency">Compounding frequency</label>
            <select id="compounding_frequency" name="compounding_frequency">
                <option value="">N/A</option>
                <?php foreach (Instrument::COMPOUNDING as $freq): ?>
                    <option value="<?= $freq ?>" <?= ($form['compounding_frequency'] ?? '') === $freq ? 'selected' : '' ?>><?= ucfirst(str_replace('-', '-', $freq)) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Used only for the fallback value estimate before you log an actual valuation.</div>
        </div>

        <div class="form-row">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Account number, nominee, anything worth remembering"><?= e($form['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn"><?= $instrument ? 'Save changes' : 'Add investment' ?></button>
            <a href="<?= $instrument ? base_url('instrument.php?id=' . $id) : base_url('instruments.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
