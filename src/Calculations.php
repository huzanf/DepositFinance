<?php

namespace DepositFinance;

/**
 * All the money math: net invested, current value estimates, and XIRR.
 *
 * Contributions are no longer something you log by hand — an FD's principal
 * and an RD/SIP's recurring installments are assumed to have gone out of your
 * account on schedule (start_date + frequency), exactly like a real bank does.
 * The transaction log is now only for exceptions: a withdrawal, a missed
 * installment (logged as a withdrawal to net it back out), an extra top-up
 * beyond the regular schedule (contribution), or money actually received
 * (maturity_payout, interest_credit).
 */
class Calculations
{
    private const OUTFLOW_TYPES = ['contribution'];
    private const INFLOW_TYPES = ['withdrawal', 'maturity_payout', 'interest_credit'];

    /**
     * Adds $months calendar months to $date, clamping the day to the target
     * month's last valid day (so 31 Jan + 1 month lands on 28/29 Feb, not 3 Mar).
     */
    public static function addMonthsClamped(string $date, int $months): string
    {
        $start = new \DateTimeImmutable($date);
        $day = (int) $start->format('d');

        $target = $start->modify('first day of this month')->modify("+{$months} months");
        $lastDayOfTargetMonth = (int) $target->format('t');
        $finalDay = min($day, $lastDayOfTargetMonth);

        return $target->modify('+' . ($finalDay - 1) . ' days')->format('Y-m-d');
    }

    private static function periodMonths(string $frequency): int
    {
        return match ($frequency) {
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };
    }

    /**
     * How many installments (or, for a one-time FD, the single principal
     * deposit) should have occurred by today — capped at the instrument's
     * maturity date once it's matured, so the count stops growing after that.
     */
    public static function scheduledInstallmentCount(array $instrument): int
    {
        $today = date('Y-m-d');
        $capDate = $instrument['maturity_date'] ?? null;
        $start = new \DateTimeImmutable($instrument['start_date']);

        if ($capDate && $capDate <= $today) {
            // The maturity date itself closes the schedule rather than being one
            // more installment date, so count up to the day before it.
            $asOf = (new \DateTimeImmutable($capDate))->modify('-1 day');
        } else {
            $asOf = new \DateTimeImmutable($today);
        }

        if ($asOf < $start) {
            return 0;
        }

        if ($instrument['frequency'] === 'one-time') {
            return 1;
        }

        $elapsedMonths = ((int) $asOf->format('Y') - (int) $start->format('Y')) * 12
            + ((int) $asOf->format('n') - (int) $start->format('n'));
        if ((int) $asOf->format('j') >= (int) $start->format('j')) {
            $elapsedMonths++;
        }

        if ($elapsedMonths < 1) {
            return 0;
        }

        $period = self::periodMonths($instrument['frequency']);
        return intdiv($elapsedMonths - 1, $period) + 1;
    }

    /** Total scheduled principal to date: the FD lump sum, or installments x count for RD/SIP. */
    public static function scheduledNetInvested(array $instrument): float
    {
        $count = self::scheduledInstallmentCount($instrument);

        if ($instrument['frequency'] === 'one-time') {
            return $count > 0 ? (float) ($instrument['principal_amount'] ?? 0) : 0.0;
        }

        return $count * (float) ($instrument['installment_amount'] ?? 0);
    }

    /** One synthetic outflow per scheduled installment, dated to when each was due. */
    public static function buildScheduledCashflows(array $instrument): array
    {
        $count = self::scheduledInstallmentCount($instrument);
        if ($count === 0) {
            return [];
        }

        if ($instrument['frequency'] === 'one-time') {
            return [[
                'date' => $instrument['start_date'],
                'amount' => -(float) ($instrument['principal_amount'] ?? 0),
            ]];
        }

        $amount = (float) ($instrument['installment_amount'] ?? 0);
        $period = self::periodMonths($instrument['frequency']);

        $flows = [];
        for ($i = 0; $i < $count; $i++) {
            $flows[] = [
                'date' => self::addMonthsClamped($instrument['start_date'], $i * $period),
                'amount' => -$amount,
            ];
        }

        return $flows;
    }

    /**
     * Net invested = the auto-calculated schedule, adjusted by any manually
     * logged exceptions (a withdrawal reduces it, an extra contribution adds
     * to it). Maturity payouts and interest credits are money received, not
     * principal, so they don't affect this figure.
     */
    public static function netInvested(array $instrument, array $transactions): float
    {
        $net = self::scheduledNetInvested($instrument);

        foreach ($transactions as $txn) {
            if ($txn['txn_type'] === 'contribution') {
                $net += (float) $txn['amount'];
            } elseif ($txn['txn_type'] === 'withdrawal') {
                $net -= (float) $txn['amount'];
            }
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

        $netInvested = self::netInvested($instrument, $transactions);
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
     * Builds an XIRR cashflow series from the instrument's scheduled installments,
     * any logged exception transactions, and its current value as a final,
     * present-day inflow.
     */
    public static function buildCashflows(array $instrument, array $transactions, float $currentValue): array
    {
        $flows = self::buildScheduledCashflows($instrument);

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
