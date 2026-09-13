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

// Finish each type's row: gain and return, and drop types with nothing in them.
foreach ($byType as $type => $totals) {
    if ($totals['invested'] <= 0 && $totals['value'] <= 0) {
        unset($byType[$type]);
        continue;
    }
    $gain = $totals['value'] - $totals['invested'];
    $byType[$type]['gain'] = $gain;
    $byType[$type]['gain_pct'] = $totals['invested'] > 0 ? ($gain / $totals['invested']) * 100 : 0.0;
}

$totalGain = $totalCurrentValue - $totalInvested;
$totalGainPct = $totalInvested > 0 ? ($totalGain / $totalInvested) * 100 : 0;

$theme = current_theme();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/partials/header.php';

/** Renders the small FD/RD/SIP breakdown list under a dashboard tile. */
function render_type_breakdown(array $byType, string $field, bool $isMoney, bool $signed): void
{
    echo '<dl class="type-breakdown">';
    foreach ($byType as $type => $totals) {
        $v = $totals[$field];
        $formatted = $isMoney ? money($v) : number_format($v, 2) . '%';
        if ($signed) {
            $formatted = ($v >= 0 ? '+' : '') . $formatted;
        }
        $cls = $signed ? ($v >= 0 ? 'positive' : 'negative') : '';
        echo '<dt><span class="badge badge-' . strtolower($type) . '">' . e($type) . '</span></dt>';
        echo '<dd class="' . $cls . '">' . $formatted . '</dd>';
    }
    echo '</dl>';
}

// Portfolio allocation donut (theme one only): each type's share of current value.
$donutColors = ['FD' => '#2f6fed', 'RD' => '#16a34a', 'SIP' => '#7c3aed'];
$donutSegments = [];
if ($theme !== 'zero' && $totalCurrentValue > 0) {
    $cumulative = 0.0;
    foreach ($byType as $type => $totals) {
        if ($totals['value'] <= 0) {
            continue;
        }
        $pct = $totals['value'] / $totalCurrentValue * 100;
        $start = $cumulative;
        $cumulative += $pct;
        $donutSegments[] = [
            'type' => $type,
            'pct' => $pct,
            'value' => $totals['value'],
            'color' => $donutColors[$type] ?? '#94a3b8',
            'start' => $start,
            'end' => $cumulative,
        ];
    }
}
$gradientParts = [];
foreach ($donutSegments as $seg) {
    $gradientParts[] = sprintf('%s %s%% %s%%', $seg['color'], number_format($seg['start'], 2), number_format($seg['end'], 2));
}
$donutGradient = empty($gradientParts) ? '#eef1f8 0% 100%' : implode(', ', $gradientParts);
?>

<?php if ($theme === 'zero'): ?>
<div class="page-header">
    <h1>Dashboard</h1>
    <a href="<?= base_url('instrument_form.php') ?>" class="btn">+ Add investment</a>
</div>
<?php else: ?>
<div class="page-header">
    <div>
        <h1 style="margin-bottom:2px"><?= e($greeting) ?>, <?= e(explode(' ', $currentUserName)[0] ?: $currentUserName) ?></h1>
        <p class="muted" style="margin:0">Here's a snapshot of your family's investments.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= base_url('instrument_form.php') ?>" class="btn"><?= icon('plus', 16) ?> Add investment</a>
    </div>
</div>
<?php endif; ?>

<?php if (empty($instruments)): ?>
    <div class="card empty-state">
        <?php if ($theme !== 'zero'): ?><div><?= icon('layers', 34) ?></div><?php endif; ?>
        <p>No investments tracked yet.</p>
        <a href="<?= base_url('instrument_form.php') ?>" class="btn">Add your first investment</a>
    </div>
<?php else: ?>

<div class="grid grid-4">
    <div class="stat">
        <div class="stat-top">
            <div class="label">Net invested</div>
            <?php if ($theme !== 'zero'): ?><span class="stat-icon blue"><?= icon('layers', 17) ?></span><?php endif; ?>
        </div>
        <div class="value"><?= money($totalInvested) ?></div>
        <?php render_type_breakdown($byType, 'invested', true, false) ?>
    </div>
    <div class="stat">
        <div class="stat-top">
            <div class="label">Current value</div>
            <?php if ($theme !== 'zero'): ?><span class="stat-icon green"><?= icon('bar-chart', 17) ?></span><?php endif; ?>
        </div>
        <div class="value"><?= money($totalCurrentValue) ?></div>
        <?php if ($hasEstimatedValues): ?><div class="sub">Includes estimated figures</div><?php endif; ?>
        <?php render_type_breakdown($byType, 'value', true, false) ?>
    </div>
    <div class="stat">
        <div class="stat-top">
            <div class="label">Overall gain</div>
            <?php if ($theme !== 'zero'): ?><span class="stat-icon amber"><?= icon('target', 17) ?></span><?php endif; ?>
        </div>
        <div class="value <?= $totalGain >= 0 ? 'positive' : 'negative' ?>">
            <?= ($totalGain >= 0 ? '+' : '') . money($totalGain) ?>
        </div>
        <?php render_type_breakdown($byType, 'gain', true, true) ?>
    </div>
    <div class="stat">
        <div class="stat-top">
            <div class="label">Return</div>
            <?php if ($theme !== 'zero'): ?><span class="stat-icon purple"><?= icon('activity', 17) ?></span><?php endif; ?>
        </div>
        <div class="value <?= $totalGainPct >= 0 ? 'positive' : 'negative' ?>">
            <?= ($totalGainPct >= 0 ? '+' : '') . number_format($totalGainPct, 2) ?>%
        </div>
        <?php render_type_breakdown($byType, 'gain_pct', false, true) ?>
    </div>
</div>

<?php if ($theme !== 'zero'): ?>
<div class="grid grid-2">
    <div class="card">
        <h2>Portfolio allocation</h2>
        <?php if (empty($donutSegments)): ?>
            <p class="muted">Nothing to show yet.</p>
        <?php else: ?>
        <div class="donut-wrap">
            <div class="donut" style="background: conic-gradient(<?= $donutGradient ?>);">
                <div class="donut-center">
                    <div class="n"><?= count($instruments) ?></div>
                    <div class="l">Instruments</div>
                </div>
            </div>
            <div class="donut-legend">
                <?php foreach ($donutSegments as $seg): ?>
                    <div class="donut-legend-row">
                        <span class="donut-dot" style="background:<?= $seg['color'] ?>"></span>
                        <span class="name"><?= e($seg['type']) ?></span>
                        <span class="pct"><?= number_format($seg['pct'], 1) ?>%</span>
                        <span class="amt"><?= money($seg['value']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Upcoming maturities <span class="muted" style="font-weight:normal">(next 60 days)</span></h2>
        <?php if (empty($upcoming)): ?>
            <div class="empty-state" style="padding:20px 10px">
                <div><?= icon('calendar', 28) ?></div>
                <p style="margin-bottom:0">Nothing coming up. Relax — no maturities in the next 60 days.</p>
            </div>
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
</div>
<?php else: ?>
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

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
