<?php

/**
 * Maturity reminder cron job. Not web-accessible — run via a scheduled cPanel Cron Job:
 *
 *   php /home/YOURUSER/depositfinance/cron/send_reminders.php
 *
 * Run it once daily. It emails the owner about instruments maturing in 60, 30 or 7 days,
 * and on the maturity day itself, each reminder sent at most once (tracked in reminders_sent).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require __DIR__ . '/../src/helpers.php';

spl_autoload_register(function (string $class) {
    $prefix = 'DepositFinance\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

date_default_timezone_set(config('app.timezone') ?? 'UTC');

use DepositFinance\Instrument;
use DepositFinance\Member;
use DepositFinance\ReminderRepo;
use DepositFinance\Mailer;

const THRESHOLDS = [60, 30, 7, 0];

$ownerEmail = trim((string) config('owner_email'));
$membersById = array_column(Member::all(), null, 'id');
$today = new DateTimeImmutable('today');
$sentCount = 0;

foreach (Instrument::activeWithMaturity() as $instrument) {
    $maturityDate = new DateTimeImmutable($instrument['maturity_date']);
    $daysUntil = (int) $today->diff($maturityDate)->format('%r%a');

    if (!in_array($daysUntil, THRESHOLDS, true)) {
        continue;
    }

    if (ReminderRepo::alreadySent((int) $instrument['id'], $daysUntil)) {
        continue;
    }

    $memberName = $instrument['member_id'] && isset($membersById[$instrument['member_id']])
        ? $membersById[$instrument['member_id']]['name']
        : null;

    $when = $daysUntil === 0 ? 'matures today' : "matures in {$daysUntil} days";
    $subject = sprintf('[DepositFinance] %s %s (%s)', $instrument['name'], $when, $instrument['type']);

    $lines = [
        "{$instrument['name']} ({$instrument['type']}) at {$instrument['institution']} {$when}.",
        '',
        'Maturity date: ' . date('d M Y', $maturityDate->getTimestamp()),
    ];
    if ($memberName) {
        $lines[] = "Member: {$memberName}";
    }
    if ($instrument['maturity_amount'] !== null) {
        $lines[] = 'Expected maturity amount: ' . money((float) $instrument['maturity_amount']);
    }
    $lines[] = '';
    $lines[] = 'View: (log in to DepositFinance and open Instruments to find it)';

    Mailer::send($ownerEmail, $subject, implode("\n", $lines));
    ReminderRepo::markSent((int) $instrument['id'], $daysUntil);
    $sentCount++;

    echo "Sent reminder for #{$instrument['id']} {$instrument['name']} ({$daysUntil} days)\n";
}

echo "Done. {$sentCount} reminder(s) sent.\n";
