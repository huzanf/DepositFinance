<?php

namespace DepositFinance;

/**
 * All the money math: net invested, current value estimates, and XIRR.
 *
 * Convention used throughout: every transaction 'amount' is stored as a positive
 * number. txn_type says which direction it moves money:
 *   - contribution        money you put IN
 *   - withdrawal          money you took OUT
 *   - maturity_payout     money you received when the instrument matured/closed
 *   - interest_credit     interest paid out to you (not reinvested)
 */
class Calculations
{
    private const OUTFLOW_TYPES = ['contribution'];
    private const INFLOW_TYPES = ['withdrawal', 'maturity_payout', 'interest_credit'];

    public static function netInvested(array $transactions): float
    {
        $net = 0.0;
        foreach ($transactions as $txn) {
            $sign = in_array($txn['txn_type'], self::OUTFLOW_TYPES, true) ? 1 : -1;
            $net += $sign * (float) $txn['amount'];
        }
        return $net;
    }

    /**
     * Returns ['value' => float, 'is_estimated' => bool, 'as_of' => string]
     *
     * Uses the latest manual valuation if one exists. Otherwise falls back to a
     * rough simple-interest projection from net invested + the instrument's rate,
     * clearly flagged as estimated so it's never confused with a real figure.
     */
    public static function currentValue(array $instrument, array $transactions, ?array $latestValuation): array
    {
        if ($latestValuation) {
            return [
                'value' => (float) $latestValuation['current_value'],
                'is_estimated' => false,
                'as_of' => $latestValuation['value_date'],
            ];
        }

        $netInvested = self::netInvested($transactions);
        $rate = $instrument['interest_rate'] !== null ? (float) $instrument['interest_rate'] : null;

        if ($netInvested <= 0 || $rate === null) {
            return [
                'value' => max($netInvested, 0.0),
                'is_estimated' => $rate !== null,
                'as_of' => date('Y-m-d'),
            ];
        }

        $start = new \DateTimeImmutable($instrument['start_date']);
        $today = new \DateTimeImmutable('today');
        $years = max(0, ($today->getTimestamp() - $start->getTimestamp()) / (365.25 * 86400));

        $estimated = $netInvested * (1 + ($rate / 100) * $years);

        return [
            'value' => $estimated,
            'is_estimated' => true,
            'as_of' => date('Y-m-d'),
        ];
    }

    /**
     * Builds an XIRR cashflow series from an instrument's transactions plus its
     * current value as a final, present-day inflow.
     */
    public static function buildCashflows(array $transactions, float $currentValue): array
    {
        $flows = [];

        foreach ($transactions as $txn) {
            $sign = in_array($txn['txn_type'], self::OUTFLOW_TYPES, true) ? -1 : 1;
            $flows[] = [
                'date' => $txn['txn_date'],
                'amount' => $sign * (float) $txn['amount'],
            ];
        }

        if ($currentValue > 0) {
            $flows[] = [
                'date' => date('Y-m-d'),
                'amount' => $currentValue,
            ];
        }

        usort($flows, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $flows;
    }

    /**
     * Annualized return via XIRR (Newton-Raphson with a bisection fallback).
     * Returns null when it can't be computed (e.g. all cashflows the same sign).
     *
     * @param array<int, array{date: string, amount: float}> $cashflows
     */
    public static function xirr(array $cashflows): ?float
    {
        $cashflows = array_values(array_filter($cashflows, fn ($f) => abs($f['amount']) > 0.0001));

        if (count($cashflows) < 2) {
            return null;
        }

        $hasPositive = false;
        $hasNegative = false;
        foreach ($cashflows as $f) {
            $f['amount'] > 0 ? $hasPositive = true : $hasNegative = true;
        }
        if (!$hasPositive || !$hasNegative) {
            return null;
        }

        $baseDate = new \DateTimeImmutable($cashflows[0]['date']);
        $years = [];
        foreach ($cashflows as $f) {
            $days = ($baseDate->diff(new \DateTimeImmutable($f['date']))->days);
            $isBefore = (new \DateTimeImmutable($f['date'])) < $baseDate;
            $years[] = ($isBefore ? -1 : 1) * $days / 365.0;
        }

        $npv = function (float $rate) use ($cashflows, $years): float {
            $total = 0.0;
            foreach ($cashflows as $i => $f) {
                $total += $f['amount'] / (1 + $rate) ** $years[$i];
            }
            return $total;
        };

        $npvDerivative = function (float $rate) use ($cashflows, $years): float {
            $total = 0.0;
            foreach ($cashflows as $i => $f) {
                if ($years[$i] == 0) {
                    continue;
                }
                $total += -$years[$i] * $f['amount'] / (1 + $rate) ** ($years[$i] + 1);
            }
            return $total;
        };

        $rate = 0.1;
        $converged = false;

        for ($i = 0; $i < 100; $i++) {
            $value = $npv($rate);
            $derivative = $npvDerivative($rate);

            if (abs($derivative) < 1e-10) {
                break;
            }

            $newRate = $rate - $value / $derivative;

            if ($newRate <= -0.999999) {
                $newRate = ($rate + -0.999999) / 2;
            }

            if (abs($newRate - $rate) < 1e-7) {
                $rate = $newRate;
                $converged = true;
                break;
            }

            $rate = $newRate;
        }

        if (!$converged || !is_finite($rate)) {
            $bisected = self::xirrBisection($npv);
            if ($bisected === null) {
                return null;
            }
            $rate = $bisected;
        }

        return $rate * 100;
    }

    private static function xirrBisection(callable $npv): ?float
    {
        $low = -0.99;
        $high = 10.0;
        $npvLow = $npv($low);
        $npvHigh = $npv($high);

        if ($npvLow == 0) {
            return $low;
        }
        if ($npvHigh == 0) {
            return $high;
        }
        if (($npvLow > 0) === ($npvHigh > 0)) {
            return null;
        }

        for ($i = 0; $i < 200; $i++) {
            $mid = ($low + $high) / 2;
            $npvMid = $npv($mid);

            if (abs($npvMid) < 1e-6) {
                return $mid;
            }

            if (($npvMid > 0) === ($npvLow > 0)) {
                $low = $mid;
                $npvLow = $npvMid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }
}
