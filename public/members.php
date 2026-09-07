<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Member;
use DepositFinance\Instrument;
use DepositFinance\TransactionRepo;
use DepositFinance\ValuationRepo;
use DepositFinance\Calculations;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        Member::delete((int) ($_POST['id'] ?? 0));
        flash('success', 'Member removed. Their instruments are kept, just unassigned.');
        redirect('/members.php');
    }
}

$members = Member::all();
$latestValuations = ValuationRepo::latestByInstrument();

$pageTitle = 'Members';
$activeNav = 'members';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <h1>Members</h1>
    <a href="<?= base_url('member_form.php') ?>" class="btn">+ Add member</a>
</div>

<p class="muted" style="margin-top:-10px">One login for the household — tag each instrument to whoever it belongs to, and see totals per person.</p>

<?php if (empty($members)): ?>
    <div class="card empty-state">
        <p>No members added yet. Add family members to tag instruments to them.</p>
        <a href="<?= base_url('member_form.php') ?>" class="btn">Add your first member</a>
    </div>
<?php else: ?>
    <div class="card" style="padding:0">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Notes</th>
                <th style="text-align:right">Current value</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $member): ?>
                <?php
                $instruments = Instrument::all(null, null, (int) $member['id']);
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
                ?>
                <tr>
                    <td><a href="<?= base_url('instruments.php?member=' . $member['id']) ?>"><?= e($member['name']) ?></a></td>
                    <td class="muted"><?= e($member['notes'] ?? '') ?></td>
                    <td style="text-align:right"><?= money($total) ?></td>
                    <td style="white-space:nowrap">
                        <a href="<?= base_url('member_form.php?id=' . $member['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Remove this member? Their instruments stay, just unassigned.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $member['id'] ?>">
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
