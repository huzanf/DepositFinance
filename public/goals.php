<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Goal;
use DepositFinance\Instrument;
use DepositFinance\TransactionRepo;
use DepositFinance\ValuationRepo;
use DepositFinance\Calculations;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        Goal::delete((int) ($_POST['id'] ?? 0));
        flash('success', 'Goal removed. Its instruments are kept, just unassigned.');
        redirect('/goals.php');
    }
}

$goals = Goal::all();
$latestValuations = ValuationRepo::latestByInstrument();

$pageTitle = 'Goals';
$activeNav = 'goals';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <h1>Goals</h1>
    <a href="<?= base_url('goal_form.php') ?>" class="btn">+ Add goal</a>
</div>

<p class="muted" style="margin-top:-10px">Tag deposits with why you're saving — "₹2L in deposits" becomes "₹70k for travel, ₹50k for insurance".</p>

<?php if (empty($goals)): ?>
    <div class="card empty-state">
        <p>No goals added yet.</p>
        <a href="<?= base_url('goal_form.php') ?>" class="btn">Add your first goal</a>
    </div>
<?php else: ?>
    <div class="card" style="padding:0">
        <table>
            <thead>
            <tr>
                <th>Goal</th>
                <th style="text-align:right">Current value</th>
                <th style="text-align:right">Target</th>
                <th style="text-align:right">Progress</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($goals as $goal): ?>
                <?php
                $instruments = Instrument::all(null, null, null, (int) $goal['id']);
                $total = 0.0;
                foreach ($instruments as $instrument) {
                    if (in_array($instrument['status'], ['closed', 'renewed'], true)) {
                        continue;
                    }
                    $transactions = TransactionRepo::forInstrument((int) $instrument['id']);
                    $latestValuation = $latestValuations[$instrument['id']] ?? null;
                    $current = Calculations::currentValue($instrument, $transactions, $latestValuation);
                    $total += $current['value'];
                }
                $target = $goal['target_amount'] !== null ? (float) $goal['target_amount'] : null;
                $progress = $target && $target > 0 ? min(100, $total / $target * 100) : null;
                ?>
                <tr>
                    <td><a href="<?= base_url('instruments.php?goal=' . $goal['id']) ?>"><?= e($goal['name']) ?></a></td>
                    <td style="text-align:right"><?= money($total) ?></td>
                    <td style="text-align:right" class="muted"><?= $target !== null ? money($target) : '—' ?></td>
                    <td style="text-align:right"><?= $progress !== null ? number_format($progress, 0) . '%' : '—' ?></td>
                    <td style="white-space:nowrap">
                        <a href="<?= base_url('goal_form.php?id=' . $goal['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Remove this goal? Its instruments stay, just unassigned.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $goal['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
