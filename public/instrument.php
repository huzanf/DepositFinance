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

$id = (int) ($_GET['id'] ?? 0);
$instrument = Instrument::find($id);

if (!$instrument) {
    flash('error', 'Instrument not found.');
    redirect('/instruments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_transaction') {
        $txnDate = $_POST['txn_date'] ?? '';
        $amount = $_POST['amount'] ?? '';
        $txnType = $_POST['txn_type'] ?? '';

        if (!strtotime($txnDate) || !is_numeric($amount) || (float) $amount <= 0 || !in_array($txnType, TransactionRepo::TYPES, true)) {
            flash('error', 'Enter a valid date, a positive amount, and a transaction type.');
        } else {
            TransactionRepo::create($id, [
                'txn_date' => $txnDate,
                'amount' => $amount,
                'txn_type' => $txnType,
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ]);
            flash('success', 'Transaction logged.');
        }
        redirect('/instrument.php?id=' . $id);
    }

    if ($action === 'delete_transaction') {
        TransactionRepo::delete((int) ($_POST['txn_id'] ?? 0), $id);
        flash('success', 'Transaction deleted.');
        redirect('/instrument.php?id=' . $id);
    }

    if ($action === 'add_valuation') {
        $valueDate = $_POST['value_date'] ?? '';
        $currentValue = $_POST['current_value'] ?? '';

        if (!strtotime($valueDate) || !is_numeric($currentValue) || (float) $currentValue < 0) {
            flash('error', 'Enter a valid date and a non-negative value.');
        } else {
            ValuationRepo::create($id, [
                'value_date' => $valueDate,
                'current_value' => $currentValue,
            ]);
            flash('success', 'Valuation recorded.');
        }
        redirect('/instrument.php?id=' . $id);
    }

    if ($action === 'delete_valuation') {
        ValuationRepo::delete((int) ($_POST['valuation_id'] ?? 0), $id);
        flash('success', 'Valuation deleted.');
        redirect('/instrument.php?id=' . $id);
    }

    if ($action === 'delete_instrument') {
        Instrument::delete($id);
        flash('success', 'Instrument deleted.');
        redirect('/instruments.php');
    }
}

$transactions = TransactionRepo::forInstrument($id);
$valuations = ValuationRepo::forInstrument($id);
$latestValuation = $valuations[0] ?? null;

$netInvested = Calculations::netInvested($transactions);
$current = Calculations::currentValue($instrument, $transactions, $latestValuation);
$gain = $current['value'] - $netInvested;
$gainPct = $netInvested > 0 ? ($gain / $netInvested) * 100 : 0;

$cashflows = Calculations::buildCashflows($transactions, $current['value']);
$xirr = Calculations::xirr($cashflows);

$member = $instrument['member_id'] ? Member::find((int) $instrument['member_id']) : null;
$goal = $instrument['goal_id'] ? Goal::find((int) $instrument['goal_id']) : null;
$renewedFrom = $instrument['renewed_from_id'] ? Instrument::find((int) $instrument['renewed_from_id']) : null;
$renewedTo = Instrument::findSuccessorOf($id);

$pageTitle = $instrument['name'];
$activeNav = 'instruments';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 style="margin-bottom:4px">
            <?= e($instrument['name']) ?>
            <span class="badge badge-<?= strtolower($instrument['type']) ?>"><?= $instrument['type'] ?></span>
            <span class="badge badge-<?= $instrument['status'] ?>"><?= ucfirst($instrument['status']) ?></span>
        </h1>
        <div class="muted"><?= e($instrument['institution']) ?></div>
    </div>
    <div>
        <?php if ($instrument['status'] !== 'renewed' && !$renewedTo): ?>
            <a href="<?= base_url('instrument_form.php?renew_from=' . $id) ?>" class="btn btn-secondary">Renew</a>
        <?php endif; ?>
        <a href="<?= base_url('instrument_form.php?id=' . $id) ?>" class="btn btn-secondary">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Delete this instrument and all its transactions/valuations? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_instrument">
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<?php if ($renewedFrom || $renewedTo): ?>
    <div class="card" style="padding:12px 20px; background:#f7f9fc;">
        <?php if ($renewedFrom): ?>
            <div>Renewed from <a href="<?= base_url('instrument.php?id=' . $renewedFrom['id']) ?>"><?= e($renewedFrom['name']) ?></a></div>
        <?php endif; ?>
        <?php if ($renewedTo): ?>
            <div>Renewed into <a href="<?= base_url('instrument.php?id=' . $renewedTo['id']) ?>"><?= e($renewedTo['name']) ?></a></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="stat">
        <div class="label">Net invested</div>
        <div class="value"><?= money($netInvested) ?></div>
    </div>
    <div class="stat">
        <div class="label">Current value</div>
        <div class="value"><?= money($current['value']) ?></div>
        <div class="sub"><?= $current['is_estimated'] ? 'Estimated as of ' . e(date('d M Y', strtotime($current['as_of']))) : 'As of ' . e(date('d M Y', strtotime($current['as_of']))) ?></div>
    </div>
    <div class="stat">
        <div class="label">Gain</div>
        <div class="value <?= $gain >= 0 ? 'positive' : 'negative' ?>">
            <?= ($gain >= 0 ? '+' : '') . money($gain) ?>
        </div>
        <div class="sub <?= $gainPct >= 0 ? 'positive' : 'negative' ?>"><?= ($gainPct >= 0 ? '+' : '') . number_format($gainPct, 2) ?>%</div>
    </div>
    <div class="stat">
        <div class="label">XIRR</div>
        <div class="value <?= $xirr === null ? '' : ($xirr >= 0 ? 'positive' : 'negative') ?>">
            <?= $xirr === null ? '—' : ($xirr >= 0 ? '+' : '') . number_format($xirr, 2) . '%' ?>
        </div>
        <div class="sub">Annualized</div>
    </div>
</div>

<div class="card">
    <h2>Details</h2>
    <dl class="detail-grid">
        <dt>Account / RD / folio number</dt><dd><?= !empty($instrument['account_number']) ? e($instrument['account_number']) : '—' ?></dd>
        <dt>Member</dt><dd><?= $member ? e($member['name']) : '—' ?></dd>
        <dt>Goal</dt><dd><?= $goal ? e($goal['name']) : '—' ?></dd>
        <dt>Tenure</dt><dd><?= $instrument['tenure_months'] !== null ? e($instrument['tenure_months']) . ' months' : '—' ?></dd>
        <dt>Start date</dt><dd><?= e(date('d M Y', strtotime($instrument['start_date']))) ?></dd>
        <dt>Maturity date</dt><dd><?= $instrument['maturity_date'] ? e(date('d M Y', strtotime($instrument['maturity_date']))) : '—' ?></dd>
        <dt>Interest rate / expected return</dt><dd><?= $instrument['interest_rate'] !== null ? e($instrument['interest_rate']) . '% p.a.' : '—' ?></dd>
        <dt>Compounding</dt><dd><?= $instrument['compounding_frequency'] ? ucfirst($instrument['compounding_frequency']) : '—' ?></dd>
        <dt>Principal / lump sum</dt><dd><?= $instrument['principal_amount'] !== null ? money((float) $instrument['principal_amount']) : '—' ?></dd>
        <dt>Recurring installment</dt><dd><?= $instrument['installment_amount'] !== null ? money((float) $instrument['installment_amount']) . ' / ' . $instrument['frequency'] : '—' ?></dd>
        <dt>Maturity amount (bank-quoted)</dt><dd><?= $instrument['maturity_amount'] !== null ? money((float) $instrument['maturity_amount']) : '—' ?></dd>
    </dl>
    <?php if (!empty($instrument['notes'])): ?>
        <p style="margin-top:14px; margin-bottom:0;"><strong>Notes:</strong> <?= nl2br(e($instrument['notes'])) ?></p>
    <?php endif; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Transactions</h2>
        <form method="post" style="margin-bottom:18px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_transaction">
            <div class="grid grid-2">
                <div class="form-row">
                    <label for="txn_date">Date</label>
                    <input type="date" id="txn_date" name="txn_date" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="form-row">
                    <label for="txn_type">Type</label>
                    <select id="txn_type" name="txn_type" required>
                        <option value="contribution">Contribution (money in)</option>
                        <option value="withdrawal">Withdrawal</option>
                        <option value="maturity_payout">Maturity payout</option>
                        <option value="interest_credit">Interest credit</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-2">
                <div class="form-row">
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" required
                           value="<?= $instrument['installment_amount'] !== null ? e($instrument['installment_amount']) : '' ?>">
                </div>
                <div class="form-row">
                    <label for="txn_notes">Notes</label>
                    <input type="text" id="txn_notes" name="notes" placeholder="Optional">
                </div>
            </div>
            <button type="submit" class="btn">Log transaction</button>
        </form>

        <?php if (empty($transactions)): ?>
            <p class="muted">No transactions logged yet.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Type</th><th style="text-align:right">Amount</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $txn): ?>
                    <tr>
                        <td><?= e(date('d M Y', strtotime($txn['txn_date']))) ?></td>
                        <td class="muted"><?= ucfirst(str_replace('_', ' ', $txn['txn_type'])) ?></td>
                        <td style="text-align:right"><?= money((float) $txn['amount']) ?></td>
                        <td>
                            <form method="post" class="inline" onsubmit="return confirm('Delete this transaction?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="txn_id" value="<?= $txn['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Valuations</h2>
        <p class="muted" style="margin-top:0">Log the actual current value whenever you check your statement — this replaces the estimate above.</p>
        <form method="post" style="margin-bottom:18px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_valuation">
            <div class="grid grid-2">
                <div class="form-row">
                    <label for="value_date">As of date</label>
                    <input type="date" id="value_date" name="value_date" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="form-row">
                    <label for="current_value">Current value</label>
                    <input type="number" step="0.01" min="0" id="current_value" name="current_value" required>
                </div>
            </div>
            <button type="submit" class="btn">Record valuation</button>
        </form>

        <?php if (empty($valuations)): ?>
            <p class="muted">No valuations recorded yet — showing an estimated current value instead.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th style="text-align:right">Value</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($valuations as $val): ?>
                    <tr>
                        <td><?= e(date('d M Y', strtotime($val['value_date']))) ?></td>
                        <td style="text-align:right"><?= money((float) $val['current_value']) ?></td>
                        <td>
                            <form method="post" class="inline" onsubmit="return confirm('Delete this valuation?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_valuation">
                                <input type="hidden" name="valuation_id" value="<?= $val['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
