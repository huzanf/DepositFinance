<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Instrument;
use DepositFinance\Member;
use DepositFinance\Goal;
use DepositFinance\TransactionRepo;
use DepositFinance\ValuationRepo;
use DepositFinance\Calculations;

Auth::requireLogin();

$instruments = Instrument::all();
$latestValuations = ValuationRepo::latestByInstrument();
$membersById = array_column(Member::all(), null, 'id');
$goalsById = array_column(Goal::all(), null, 'id');

$statusCounts = ['active' => 0, 'matured' => 0, 'renewed' => 0, 'closed' => 0];
$monthlyCommitment = 0.0;
$totalInvested = 0.0;
$totalCurrentValue = 0.0;
$totalExpectedMaturity = 0.0;
$byGoal = [];
$byInstitution = [];
$byMember = [];
$maturitySchedule = [];

foreach ($instruments as $instrument) {
    $statusCounts[$instrument['status']] = ($statusCounts[$instrument['status']] ?? 0) + 1;

    if ($instrument['status'] === 'active' && $instrument['frequency'] === 'monthly' && $instrument['installment_amount'] !== null) {
        $monthlyCommitment += (float) $instrument['installment_amount'];
    }

    if (in_array($instrument['status'], ['closed', 'renewed'], true)) {
        continue;
    }

    $transactions = TransactionRepo::forInstrument((int) $instrument['id']);
    $latestValuation = $latestValuations[$instrument['id']] ?? null;
    $netInvested = Calculations::netInvested($transactions);
    $current = Calculations::currentValue($instrument, $transactions, $latestValuation);
    $expectedMaturity = $instrument['maturity_amount'] !== null ? (float) $instrument['maturity_amount'] : $current['value'];

    $totalInvested += $netInvested;
    $totalCurrentValue += $current['value'];
    $totalExpectedMaturity += $expectedMaturity;

    $goalName = $instrument['goal_id'] && isset($goalsById[$instrument['goal_id']]) ? $goalsById[$instrument['goal_id']]['name'] : 'Unassigned';
    $byGoal[$goalName] = ($byGoal[$goalName] ?? 0) + $current['value'];

    $byInstitution[$instrument['institution']] = ($byInstitution[$instrument['institution']] ?? 0) + $current['value'];

    $memberName = $instrument['member_id'] && isset($membersById[$instrument['member_id']]) ? $membersById[$instrument['member_id']]['name'] : 'Unassigned';
    $byMember[$memberName] = ($byMember[$memberName] ?? 0) + $current['value'];

    if ($instrument['status'] === 'active' && $instrument['maturity_date']) {
        $maturitySchedule[] = $instrument;
    }
}

arsort($byGoal);
arsort($byInstitution);
arsort($byMember);
usort($maturitySchedule, fn ($a, $b) => strcmp($a['maturity_date'], $b['maturity_date']));

$pageTitle = 'Reports';
$activeNav = 'reports';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <h1>Reports</h1>
    <a href="<?= base_url('reports_export.php') ?>" class="btn btn-secondary">Export CSV</a>
</div>

<div class="grid grid-4">
    <div class="stat">
        <div class="label">Active instruments</div>
        <div class="value"><?= $statusCounts['active'] ?></div>
        <div class="sub muted">
            <?= $statusCounts['matured'] ?> matured &middot; <?= $statusCounts['renewed'] ?> renewed &middot; <?= $statusCounts['closed'] ?> closed
        </div>
    </div>
    <div class="stat">
        <div class="label">Monthly commitment</div>
        <div class="value"><?= money($monthlyCommitment) ?></div>
        <div class="sub muted">Active RD/SIP installments</div>
    </div>
    <div class="stat">
        <div class="label">Total deposited</div>
        <div class="value"><?= money($totalInvested) ?></div>
    </div>
    <div class="stat">
        <div class="label">Expected maturity value</div>
        <div class="value"><?= money($totalExpectedMaturity) ?></div>
        <div class="sub muted">Bank-quoted where set, else current value</div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>By goal</h2>
        <?php if (empty($byGoal)): ?>
            <p class="muted">Nothing to show.</p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($byGoal as $name => $value): ?>
                    <tr>
                        <td><?= e($name) ?></td>
                        <td style="text-align:right"><?= money($value) ?></td>
                        <td style="text-align:right" class="muted"><?= $totalCurrentValue > 0 ? number_format($value / $totalCurrentValue * 100, 1) : '0.0' ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>By member</h2>
        <?php if (empty($byMember)): ?>
            <p class="muted">Nothing to show.</p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($byMember as $name => $value): ?>
                    <tr>
                        <td><?= e($name) ?></td>
                        <td style="text-align:right"><?= money($value) ?></td>
                        <td style="text-align:right" class="muted"><?= $totalCurrentValue > 0 ? number_format($value / $totalCurrentValue * 100, 1) : '0.0' ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>By institution</h2>
    <?php if (empty($byInstitution)): ?>
        <p class="muted">Nothing to show.</p>
    <?php else: ?>
        <table>
            <tbody>
            <?php foreach ($byInstitution as $name => $value): ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <td style="text-align:right"><?= money($value) ?></td>
                    <td style="text-align:right" class="muted"><?= $totalCurrentValue > 0 ? number_format($value / $totalCurrentValue * 100, 1) : '0.0' ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Maturity schedule <span class="muted" style="font-weight:normal">(active instruments)</span></h2>
    <?php if (empty($maturitySchedule)): ?>
        <p class="muted">Nothing scheduled.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th>Name</th><th>Type</th><th style="text-align:right">Maturity amount</th></tr></thead>
            <tbody>
            <?php foreach ($maturitySchedule as $instrument): ?>
                <tr>
                    <td><?= e(date('d M Y', strtotime($instrument['maturity_date']))) ?></td>
                    <td><a href="<?= base_url('instrument.php?id=' . $instrument['id']) ?>"><?= e($instrument['name']) ?></a></td>
                    <td><span class="badge badge-<?= strtolower($instrument['type']) ?>"><?= $instrument['type'] ?></span></td>
                    <td style="text-align:right"><?= $instrument['maturity_amount'] !== null ? money((float) $instrument['maturity_amount']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
