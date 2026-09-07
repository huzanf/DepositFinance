<?php

require __DIR__ . '/../src/bootstrap.php';

use DepositFinance\Auth;
use DepositFinance\Instrument;

Auth::requireLogin();

$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$monthStart = new DateTimeImmutable($monthParam . '-01');
$monthEnd = $monthStart->modify('last day of this month');
$prevMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');

$maturing = Instrument::maturingBetween($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'));

$byDay = [];
foreach ($maturing as $instrument) {
    $day = (int) date('j', strtotime($instrument['maturity_date']));
    $byDay[$day][] = $instrument;
}

$firstWeekday = (int) $monthStart->format('w'); // 0 = Sunday
$daysInMonth = (int) $monthStart->format('t');
$today = date('Y-m-d');

$pageTitle = 'Calendar';
$activeNav = 'calendar';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <h1>Maturity Calendar</h1>
    <div>
        <a href="<?= base_url('calendar.php?month=' . $prevMonth) ?>" class="btn btn-secondary">&larr; Prev</a>
        <a href="<?= base_url('calendar.php') ?>" class="btn btn-secondary"><?= e($monthStart->format('F Y')) ?></a>
        <a href="<?= base_url('calendar.php?month=' . $nextMonth) ?>" class="btn btn-secondary">Next &rarr;</a>
    </div>
</div>

<div class="card">
    <div class="cal-grid cal-weekdays">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd): ?>
            <div class="cal-weekday"><?= $wd ?></div>
        <?php endforeach; ?>
    </div>
    <div class="cal-grid">
        <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
            <div class="cal-cell cal-empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php
            $cellDate = $monthStart->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $isToday = $cellDate === $today;
            $events = $byDay[$day] ?? [];
            ?>
            <div class="cal-cell <?= $isToday ? 'cal-today' : '' ?>">
                <div class="cal-daynum"><?= $day ?></div>
                <?php foreach ($events as $ev): ?>
                    <a class="cal-event badge-<?= strtolower($ev['type']) ?>" href="<?= base_url('instrument.php?id=' . $ev['id']) ?>" title="<?= e($ev['name']) ?>">
                        <?= e($ev['type']) ?>: <?= e($ev['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<?php if (!empty($maturing)): ?>
<div class="card">
    <h2>Maturing this month</h2>
    <table>
        <thead><tr><th>Date</th><th>Name</th><th>Type</th><th style="text-align:right">Maturity amount</th></tr></thead>
        <tbody>
        <?php foreach ($maturing as $ev): ?>
            <tr>
                <td><?= e(date('d M Y', strtotime($ev['maturity_date']))) ?></td>
                <td><a href="<?= base_url('instrument.php?id=' . $ev['id']) ?>"><?= e($ev['name']) ?></a></td>
                <td><span class="badge badge-<?= strtolower($ev['type']) ?>"><?= $ev['type'] ?></span></td>
                <td style="text-align:right"><?= $ev['maturity_amount'] !== null ? money((float) $ev['maturity_amount']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
