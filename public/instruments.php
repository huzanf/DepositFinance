<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Instrument;
use DepositFinance\TransactionRepo;
use DepositFinance\ValuationRepo;
use DepositFinance\Calculations;

Auth::requireLogin();

$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$instruments = Instrument::all($typeFilter ?: null, $statusFilter ?: null);
$latestValuations = ValuationRepo::latestByInstrument();

$pageTitle = 'Instruments';
$activeNav = 'instruments';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <h1>Instruments</h1>
    <a href="<?= base_url('instrument_form.php') ?>" class="btn">+ Add investment</a>
</div>

<form method="get" class="filters">
    <select name="type" onchange="this.form.submit()">
        <option value="">All types</option>
        <?php foreach (Instrument::TYPES as $type): ?>
            <option value="<?= $type ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= $type ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach (Instrument::STATUSES as $status): ?>
            <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="card" style="padding:0">
<?php if (empty($instruments)): ?>
    <div class="empty-state">
        <p>No instruments match this filter.</p>
    </div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Institution</th>
            <th>Status</th>
            <th style="text-align:right">Net invested</th>
            <th style="text-align:right">Current value</th>
            <th style="text-align:right">Gain</th>
            <th>Maturity</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($instruments as $instrument): ?>
            <?php
            $transactions = TransactionRepo::forInstrument((int) $instrument['id']);
            $latestValuation = $latestValuations[$instrument['id']] ?? null;
            $netInvested = Calculations::netInvested($transactions);
            $current = Calculations::currentValue($instrument, $transactions, $latestValuation);
            $gain = $current['value'] - $netInvested;
            ?>
            <tr>
                <td><a href="<?= base_url('instrument.php?id=' . $instrument['id']) ?>"><?= e($instrument['name']) ?></a></td>
                <td><span class="badge badge-<?= strtolower($instrument['type']) ?>"><?= $instrument['type'] ?></span></td>
                <td><?= e($instrument['institution']) ?></td>
                <td><span class="badge badge-<?= $instrument['status'] ?>"><?= ucfirst($instrument['status']) ?></span></td>
                <td style="text-align:right"><?= money($netInvested) ?></td>
                <td style="text-align:right">
                    <?= money($current['value']) ?>
                    <?php if ($current['is_estimated']): ?><span class="muted" title="Estimated — add a valuation for an exact figure">*</span><?php endif; ?>
                </td>
                <td style="text-align:right" class="<?= $gain >= 0 ? 'positive' : 'negative' ?>">
                    <?= ($gain >= 0 ? '+' : '') . money($gain) ?>
                </td>
                <td class="muted"><?= $instrument['maturity_date'] ? e(date('d M Y', strtotime($instrument['maturity_date']))) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
