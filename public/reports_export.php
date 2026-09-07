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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="depositfinance-export-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, [
    'Name', 'Type', 'Institution', 'Account Number', 'Member', 'Goal', 'Status',
    'Start Date', 'Maturity Date', 'Interest Rate %', 'Net Invested', 'Current Value',
    'Gain', 'XIRR %', 'Maturity Amount',
]);

foreach ($instruments as $instrument) {
    $transactions = TransactionRepo::forInstrument((int) $instrument['id']);
    $latestValuation = $latestValuations[$instrument['id']] ?? null;
    $netInvested = Calculations::netInvested($transactions);
    $current = Calculations::currentValue($instrument, $transactions, $latestValuation);
    $gain = $current['value'] - $netInvested;
    $cashflows = Calculations::buildCashflows($transactions, $current['value']);
    $xirr = Calculations::xirr($cashflows);

    fputcsv($out, [
        $instrument['name'],
        $instrument['type'],
        $instrument['institution'],
        $instrument['account_number'] ?? '',
        $instrument['member_id'] && isset($membersById[$instrument['member_id']]) ? $membersById[$instrument['member_id']]['name'] : '',
        $instrument['goal_id'] && isset($goalsById[$instrument['goal_id']]) ? $goalsById[$instrument['goal_id']]['name'] : '',
        ucfirst($instrument['status']),
        $instrument['start_date'],
        $instrument['maturity_date'] ?? '',
        $instrument['interest_rate'] ?? '',
        number_format($netInvested, 2, '.', ''),
        number_format($current['value'], 2, '.', ''),
        number_format($gain, 2, '.', ''),
        $xirr !== null ? number_format($xirr, 2, '.', '') : '',
        $instrument['maturity_amount'] ?? '',
    ]);
}

fclose($out);
