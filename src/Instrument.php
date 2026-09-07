<?php

namespace DepositFinance;

class Instrument
{
    public const TYPES = ['FD', 'RD', 'SIP'];
    public const STATUSES = ['active', 'matured', 'renewed', 'closed'];
    public const FREQUENCIES = ['monthly', 'quarterly', 'yearly', 'one-time'];
    public const COMPOUNDING = ['monthly', 'quarterly', 'half-yearly', 'yearly'];

    public static function all(?string $type = null, ?string $status = null, ?int $memberId = null, ?int $goalId = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM instruments WHERE 1=1';
        $params = [];

        if ($type && in_array($type, self::TYPES, true)) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        if ($status && in_array($status, self::STATUSES, true)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        if ($memberId) {
            $sql .= ' AND member_id = ?';
            $params[] = $memberId;
        }

        if ($goalId) {
            $sql .= ' AND goal_id = ?';
            $params[] = $goalId;
        }

        $sql .= ' ORDER BY status = "active" DESC, COALESCE(maturity_date, "9999-12-31") ASC, name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM instruments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The instrument that renewed FROM $id, if any (reverse lookup of renewed_from_id). */
    public static function findSuccessorOf(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM instruments WHERE renewed_from_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Instruments maturing within an inclusive date range, excluding already-renewed/closed ones. */
    public static function maturingBetween(string $startDate, string $endDate): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM instruments
             WHERE maturity_date BETWEEN ? AND ?
               AND status NOT IN ('renewed', 'closed')
             ORDER BY maturity_date ASC"
        );
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }

    /** Active/matured instruments with a maturity date, for reminder scanning. */
    public static function activeWithMaturity(): array
    {
        return Database::connection()->query(
            "SELECT * FROM instruments WHERE status IN ('active', 'matured') AND maturity_date IS NOT NULL"
        )->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO instruments
                (type, name, institution, account_number, member_id, goal_id, start_date, maturity_date,
                 interest_rate, compounding_frequency, principal_amount, installment_amount, frequency,
                 tenure_months, maturity_amount, status, renewed_from_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(self::bindParams($data));

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE instruments SET
                type = ?, name = ?, institution = ?, account_number = ?, member_id = ?, goal_id = ?,
                start_date = ?, maturity_date = ?, interest_rate = ?, compounding_frequency = ?,
                principal_amount = ?, installment_amount = ?, frequency = ?, tenure_months = ?,
                maturity_amount = ?, status = ?, renewed_from_id = ?, notes = ?
             WHERE id = ?'
        );
        $stmt->execute([...self::bindParams($data), $id]);
    }

    private static function bindParams(array $data): array
    {
        return [
            $data['type'],
            $data['name'],
            $data['institution'],
            $data['account_number'] !== '' ? $data['account_number'] : null,
            !empty($data['member_id']) ? $data['member_id'] : null,
            !empty($data['goal_id']) ? $data['goal_id'] : null,
            $data['start_date'],
            $data['maturity_date'] ?: null,
            $data['interest_rate'] !== '' ? $data['interest_rate'] : null,
            $data['compounding_frequency'] ?: null,
            $data['principal_amount'] !== '' ? $data['principal_amount'] : null,
            $data['installment_amount'] !== '' ? $data['installment_amount'] : null,
            $data['frequency'],
            $data['tenure_months'] !== '' ? $data['tenure_months'] : null,
            $data['maturity_amount'] !== '' ? $data['maturity_amount'] : null,
            $data['status'],
            !empty($data['renewed_from_id']) ? $data['renewed_from_id'] : null,
            $data['notes'] ?: null,
        ];
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM instruments WHERE id = ?')->execute([$id]);
    }

    public static function markRenewed(int $id): void
    {
        Database::connection()->prepare("UPDATE instruments SET status = 'renewed' WHERE id = ?")->execute([$id]);
    }
}
