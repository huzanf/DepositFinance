<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Instrument;
use DepositFinance\TransactionRepo;
use DepositFinance\ValuationRepo;
use DepositFinance\Calculations;

Auth::requireLogin();

$instruments = Instrument::all();
$latestValuations = ValuationRepo::latestByInstrument();

$totalInvested = 0.0;
$totalCurrentValue = 0.0;
$byType = [
    'FD' => ['invested' => 0.0, 'value' => 0.0],
    'RD' => ['invested' => 0.0, 'value' => 0.0],
    'SIP' => ['invested' => 0.0, 'value' => 0.0],
];
$upcoming = [];
$hasEstimatedValues = false;

foreach ($instruments as $instrument) {
    // 'renewed' instruments have had their value carried forward into a successor
    // instrument, and 'closed' ones are done — neither belongs in live totals.
    if (in_array($instrument['status'], ['closed', 'renewed'], true)) {
        continue;
    }

    $transactions = TransactionRepo::forInstrument((int) $instrument['id']);
    $latestValuation = $latestValuations[$instrument['id']] ?? null;

    $netInvested = Calculations::netInvested($instrument, $transactions);
    $current = Calculations::currentValue($instrument, $transactions, $latestValuation);

    $totalInvested += $netInvested;
    $totalCurrentValue += $current['value'];
    $byType[$instrument['type']]['invested'] += $netInvested;
    $byType[$instrument['type']]['value'] += $current['value'];

    if ($current['is_estimated']) {
        $hasEstimatedValues = true;
    }

    if ($instrument['maturity_date']) {
        $daysUntil = (int) floor((strtotime($instrument['maturity_date']) - strtotime('today')) / 86400);
        if ($daysUntil >= 0 && $daysUntil <= 60) {
            $upcoming[] = $instrument + ['days_until' => $daysUntil];
        }
    }
}

usort($upcoming, fn ($a, $b) => $a['days_until'] <=> $b['days_until']);

$totalGain = $totalCurrentValue - $totalInvested;
$totalGainPct = $totalInvested > 0 ? ($totalGain / $totalInvested) * 100 : 0;

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <a href="<?= base_url('instrument_form.php') ?>" class="btn">+ Add investment</a>
</div>

<?php if (empty($instruments)): ?>
    <div class="card empty-state">
        <p>No investments tracked yet.</p>
        <a href="<?= base_url('instrument_form.php') ?>" class="btn">Add your first investment</a>
    </div>
<?php else: ?>

<div class="grid grid-4">
    <div class="stat">
        <div class="label">Net invested</div>
        <div class="value"><?= money($totalInvested) ?></div>
    </div>
    <div class="stat">
        <div class="label">Current value</div>
        <div class="value"><?= money($totalCurrentValue) ?></div>
        <?php if ($hasEstimatedValues): ?><div class="sub">Includes estimated figures</div><?php endif; ?>
    </div>
    <div class="stat">
        <div class="label">Overall gain</div>
        <div class="value <?= $totalGain >= 0 ? 'positive' : 'negative' ?>">
            <?= ($totalGain >= 0 ? '+' : '') . money($totalGain) ?>
        </div>
    </div>
    <div class="stat">
        <div class="label">Return</div>
        <div class="value <?= $totalGainPct >= 0 ? 'positive' : 'negative' ?>">
            <?= ($totalGainPct >= 0 ? '+' : '') . number_format($totalGainPct, 2) ?>%
        </div>
    </div>
</div>

<div class="card">
    <h2>By instrument type</h2>
    <div class="grid grid-4">
        <?php foreach ($byType as $type => $totals): ?>
            <?php if ($totals['invested'] <= 0 && $totals['value'] <= 0) continue; ?>
            <?php
            $typeGain = $totals['value'] - $totals['invested'];
            $typeGainPct = $totals['invested'] > 0 ? ($typeGain / $totals['invested']) * 100 : 0;
            ?>
            <div class="stat">
                <div class="label"><span class="badge badge-<?= strtolower($type) ?>"><?= $type ?></span></div>
                <dl class="type-breakdown">
                    <dt>Net invested</dt><dd><?= money($totals['invested']) ?></dd>
                    <dt>Current value</dt><dd><?= money($totals['value']) ?></dd>
                    <dt>Gain</dt>
                    <dd class="<?= $typeGain >= 0 ? 'positive' : 'negative' ?>"><?= ($typeGain >= 0 ? '+' : '') . money($typeGain) ?></dd>
                    <dt>Return</dt>
                    <dd class="<?= $typeGainPct >= 0 ? 'positive' : 'negative' ?>"><?= ($typeGainPct >= 0 ? '+' : '') . number_format($typeGainPct, 2) ?>%</dd>
                </dl>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h2>Upcoming maturities / due dates <span class="muted" style="font-weight:normal">(next 60 days)</span></h2>
    <?php if (empty($upcoming)): ?>
        <p class="muted">Nothing coming up.</p>
    <?php else: ?>
        <table>
            <tbody>
            <?php foreach ($upcoming as $u): ?>
                <tr>
                    <td><a href="<?= base_url('instrument.php?id=' . $u['id']) ?>"><?= e($u['name']) ?></a></td>
                    <td class="muted"><?= e(date('d M Y', strtotime($u['maturity_date']))) ?></td>
                    <td style="text-align:right">
                        <?= $u['days_until'] === 0 ? 'Today' : 'in ' . $u['days_until'] . ' days' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
