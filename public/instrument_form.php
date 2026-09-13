<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Instrument;
use DepositFinance\Member;
use DepositFinance\Goal;
use DepositFinance\Calculations;

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$instrument = $id ? Instrument::find($id) : null;

if ($id && !$instrument) {
    flash('error', 'Instrument not found.');
    redirect('/instruments.php');
}

$renewFromId = isset($_GET['renew_from']) ? (int) $_GET['renew_from'] : null;
$renewFrom = $renewFromId ? Instrument::find($renewFromId) : null;

if ($renewFromId && !$renewFrom) {
    flash('error', 'Instrument to renew was not found.');
    redirect('/instruments.php');
}

$errors = [];

if ($instrument) {
    $form = $instrument;
} elseif ($renewFrom) {
    $form = [
        'type' => $renewFrom['type'],
        'name' => $renewFrom['name'],
        'institution' => $renewFrom['institution'],
        'account_number' => '',
        'member_id' => $renewFrom['member_id'],
        'goal_id' => $renewFrom['goal_id'],
        'start_date' => $renewFrom['maturity_date'] ?: date('Y-m-d'),
        'maturity_date' => '',
        'interest_rate' => $renewFrom['interest_rate'] ?? '',
        'compounding_frequency' => $renewFrom['compounding_frequency'] ?? 'quarterly',
        'principal_amount' => $renewFrom['principal_amount'] ?? '',
        'installment_amount' => $renewFrom['installment_amount'] ?? '',
        'frequency' => $renewFrom['frequency'],
        'tenure_months' => $renewFrom['tenure_months'] ?? '',
        'maturity_amount' => '',
        'status' => 'active',
        'renewed_from_id' => $renewFrom['id'],
        'notes' => '',
    ];
} else {
    $form = [
        'type' => 'FD',
        'name' => '',
        'institution' => '',
        'account_number' => '',
        'member_id' => null,
        'goal_id' => null,
        'start_date' => date('Y-m-d'),
        'maturity_date' => '',
        'interest_rate' => '',
        'compounding_frequency' => 'quarterly',
        'principal_amount' => '',
        'installment_amount' => '',
        'frequency' => 'monthly',
        'tenure_months' => '',
        'maturity_amount' => '',
        'status' => 'active',
        'renewed_from_id' => null,
        'notes' => '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $tenureYears = max(0, min(30, (int) ($_POST['tenure_years'] ?? 0)));
    $tenureMonthsPart = max(0, min(11, (int) ($_POST['tenure_months_part'] ?? 0)));
    $tenureTotalMonths = $tenureYears * 12 + $tenureMonthsPart;

    $form = [
        'type' => $_POST['type'] ?? '',
        'name' => trim((string) ($_POST['name'] ?? '')),
        'institution' => trim((string) ($_POST['institution'] ?? '')),
        'account_number' => trim((string) ($_POST['account_number'] ?? '')),
        'member_id' => $_POST['member_id'] !== '' ? (int) $_POST['member_id'] : null,
        'goal_id' => $_POST['goal_id'] !== '' ? (int) $_POST['goal_id'] : null,
        'start_date' => $_POST['start_date'] ?? '',
        'maturity_date' => $_POST['maturity_date'] ?? '',
        'interest_rate' => trim((string) ($_POST['interest_rate'] ?? '')),
        'compounding_frequency' => $_POST['compounding_frequency'] ?? '',
        'principal_amount' => trim((string) ($_POST['principal_amount'] ?? '')),
        'installment_amount' => trim((string) ($_POST['installment_amount'] ?? '')),
        'frequency' => $_POST['frequency'] ?? 'monthly',
        'tenure_months' => $tenureTotalMonths > 0 ? (string) $tenureTotalMonths : '',
        'maturity_amount' => trim((string) ($_POST['maturity_amount'] ?? '')),
        'status' => $_POST['status'] ?? 'active',
        'renewed_from_id' => $_POST['renewed_from_id'] !== '' ? (int) $_POST['renewed_from_id'] : null,
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    // Maturity date is auto-calculated from start date + tenure when left blank —
    // still overridable by typing an explicit date, e.g. if the bank's actual
    // certificate lands a day or two off due to processing.
    if ($form['maturity_date'] === '' && $tenureTotalMonths > 0 && strtotime($form['start_date'])) {
        $form['maturity_date'] = Calculations::addMonthsClamped($form['start_date'], $tenureTotalMonths);
    }

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
    if ($form['maturity_amount'] !== '' && !is_numeric($form['maturity_amount'])) {
        $errors[] = 'Maturity amount must be a number.';
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
            if (!empty($form['renewed_from_id'])) {
                Instrument::markRenewed((int) $form['renewed_from_id']);
            }
            flash('success', 'Instrument added.');
            redirect('/instrument.php?id=' . $newId);
        }
    }
}

$members = Member::all();
$goals = Goal::all();
$institutions = Instrument::distinctInstitutions();

$tenureTotalForDisplay = (int) ($form['tenure_months'] ?? 0);
$selectedTenureYears = intdiv($tenureTotalForDisplay, 12);
$selectedTenureMonthsPart = $tenureTotalForDisplay % 12;

$theme = current_theme();
$pageTitle = $instrument ? 'Edit instrument' : ($renewFrom ? 'Renew instrument' : 'Add investment');
$activeNav = 'instruments';
require __DIR__ . '/partials/header.php';
?>

<h1><?= $instrument ? 'Edit instrument' : ($renewFrom ? 'Renew "' . e($renewFrom['name']) . '"' : 'Add investment') ?></h1>

<?php if ($renewFrom): ?>
    <p class="muted" style="margin-top:-10px">
        Creating a new instrument linked to this one. Once saved, <strong><?= e($renewFrom['name']) ?></strong> will be marked as renewed.
    </p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="flash flash-error">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($theme !== 'zero'): ?>
<div class="steps" id="wizard-steps">
    <div class="step active" data-step-indicator="1"><span class="step-num">1</span><span class="step-label">Basic info</span></div>
    <div class="step-connector"></div>
    <div class="step" data-step-indicator="2"><span class="step-num">2</span><span class="step-label">Value &amp; returns</span></div>
    <div class="step-connector"></div>
    <div class="step" data-step-indicator="3"><span class="step-num">3</span><span class="step-label">Review</span></div>
</div>
<?php endif; ?>

<div class="card">
    <p class="form-legend"><span class="req">*</span> Required</p>
    <form method="post" id="instrument-form">
        <?= csrf_field() ?>
        <input type="hidden" name="renewed_from_id" value="<?= e((string) ($form['renewed_from_id'] ?? '')) ?>">

        <div class="wizard-panel active" data-step="1">
        <div class="grid grid-2">
            <div class="form-row">
                <label for="type">Type<?= req() ?></label>
                <select id="type" name="type" required>
                    <?php foreach (Instrument::TYPES as $type): ?>
                        <option value="<?= $type ?>" <?= $form['type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="status">Status<?= req() ?></label>
                <select id="status" name="status" required>
                    <?php foreach (Instrument::STATUSES as $status): ?>
                        <option value="<?= $status ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="name">Name<?= req() ?></label>
                <input type="text" id="name" name="name" required placeholder="e.g. HDFC 3-year FD"
                       value="<?= e($form['name']) ?>">
            </div>
            <div class="form-row">
                <label for="institution">Institution<?= req() ?></label>
                <input type="text" id="institution" name="institution" required placeholder="e.g. HDFC Bank"
                       list="institution-list" value="<?= e($form['institution']) ?>">
                <datalist id="institution-list">
                    <?php foreach ($institutions as $inst): ?>
                        <option value="<?= e($inst) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="account_number">Account / RD / folio number</label>
                <input type="text" id="account_number" name="account_number" placeholder="As issued by the bank/AMC"
                       value="<?= e($form['account_number'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="member_id">Member</label>
                <select id="member_id" name="member_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= (string) ($form['member_id'] ?? '') === (string) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($members)): ?><div class="hint">No members yet — <a href="<?= base_url('member_form.php') ?>">add one</a>.</div><?php endif; ?>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="form-row">
                <label for="goal_id">Goal</label>
                <select id="goal_id" name="goal_id">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($goals as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= (string) ($form['goal_id'] ?? '') === (string) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($goals)): ?><div class="hint">No goals yet — <a href="<?= base_url('goal_form.php') ?>">add one</a>.</div><?php endif; ?>
            </div>
            <div class="form-row">
                <label for="tenure_years">Tenure</label>
                <div class="tenure-fields">
                    <select id="tenure_years" name="tenure_years">
                        <?php for ($y = 0; $y <= 30; $y++): ?>
                            <option value="<?= $y ?>" <?= $selectedTenureYears === $y ? 'selected' : '' ?>><?= $y ?> yr<?= $y === 1 ? '' : 's' ?></option>
                        <?php endfor; ?>
                    </select>
                    <select id="tenure_months_part" name="tenure_months_part">
                        <?php for ($m = 0; $m <= 11; $m++): ?>
                            <option value="<?= $m ?>" <?= $selectedTenureMonthsPart === $m ? 'selected' : '' ?>><?= $m ?> mo<?= $m === 1 ? '' : 's' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="hint">Optional.</div>
            </div>
        </div>
        <?php if ($theme !== 'zero'): ?>
        <div class="form-actions">
            <button type="button" class="btn wizard-next" data-goto="2">Next: Value &amp; returns</button>
        </div>
        <?php endif; ?>
        </div><?php // .wizard-panel[1] ?>

        <div class="wizard-panel" data-step="2">
        <div class="grid grid-2">
            <div class="form-row">
                <label for="start_date">Start date<?= req() ?></label>
                <input type="date" id="start_date" name="start_date" required value="<?= e($form['start_date']) ?>">
            </div>
            <div class="form-row">
                <label for="maturity_date">Maturity date</label>
                <input type="date" id="maturity_date" name="maturity_date" value="<?= e($form['maturity_date'] ?? '') ?>">
                <div class="hint">Leave blank to auto-calculate from start date + tenure (or for an open-ended SIP).</div>
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

        <div class="grid grid-2">
            <div class="form-row">
                <label for="compounding_frequency">Compounding frequency</label>
                <select id="compounding_frequency" name="compounding_frequency">
                    <option value="">N/A</option>
                    <?php foreach (Instrument::COMPOUNDING as $freq): ?>
                        <option value="<?= $freq ?>" <?= ($form['compounding_frequency'] ?? '') === $freq ? 'selected' : '' ?>><?= ucfirst($freq) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Used only for the fallback value estimate before you log an actual valuation.</div>
            </div>
            <div class="form-row">
                <label for="maturity_amount">Maturity amount (bank-quoted)</label>
                <input type="number" step="0.01" id="maturity_amount" name="maturity_amount"
                       placeholder="Optional — what the bank says you'll receive" value="<?= e($form['maturity_amount'] ?? '') ?>">
            </div>
        </div>
        <?php if ($theme !== 'zero'): ?>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary wizard-prev" data-goto="1">Back</button>
            <button type="button" class="btn wizard-next" data-goto="3">Next: Review</button>
        </div>
        <?php endif; ?>
        </div><?php // .wizard-panel[2] ?>

        <div class="wizard-panel" data-step="3">
        <div class="form-row">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Nominee, anything worth remembering"><?= e($form['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <?php if ($theme !== 'zero'): ?>
                <button type="button" class="btn btn-secondary wizard-prev" data-goto="2">Back</button>
            <?php endif; ?>
            <button type="submit" class="btn"><?= $instrument ? 'Save changes' : ($renewFrom ? 'Create renewed instrument' : 'Add investment') ?></button>
            <a href="<?= $instrument ? base_url('instrument.php?id=' . $id) : base_url('instruments.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
        </div><?php // .wizard-panel[3] ?>
    </form>
</div>

<?php if ($theme !== 'zero'): ?>
<script>
(function () {
    var panels = Array.prototype.slice.call(document.querySelectorAll('.wizard-panel'));
    var stepPills = Array.prototype.slice.call(document.querySelectorAll('#wizard-steps .step'));

    function goTo(step) {
        panels.forEach(function (p) {
            p.classList.toggle('active', p.getAttribute('data-step') === String(step));
        });
        stepPills.forEach(function (s) {
            var n = s.getAttribute('data-step-indicator');
            s.classList.remove('active', 'done');
            if (n === String(step)) {
                s.classList.add('active');
            } else if (parseInt(n, 10) < step) {
                s.classList.add('done');
            }
        });
        var panel = document.querySelector('.wizard-panel[data-step="' + step + '"]');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function validatePanel(panel) {
        var inputs = panel.querySelectorAll('input, select, textarea');
        for (var i = 0; i < inputs.length; i++) {
            if (!inputs[i].checkValidity()) {
                inputs[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    document.querySelectorAll('.wizard-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = btn.closest('.wizard-panel');
            if (current && !validatePanel(current)) {
                return;
            }
            goTo(btn.getAttribute('data-goto'));
        });
    });

    document.querySelectorAll('.wizard-prev').forEach(function (btn) {
        btn.addEventListener('click', function () {
            goTo(btn.getAttribute('data-goto'));
        });
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
