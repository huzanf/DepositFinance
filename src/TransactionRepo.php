<?php

namespace DepositFinance;

class TransactionRepo
{
    public const TYPES = ['contribution', 'withdrawal', 'maturity_payout', 'interest_credit'];

    /** Types that represent money going INTO the instrument (out of your pocket). */
    public const INFLOW_TYPES = ['contribution'];

    public static function forInstrument(int $instrumentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM transactions WHERE instrument_id = ? ORDER BY txn_date DESC, id DESC'
        );
        $stmt->execute([$instrumentId]);
        return $stmt->fetchAll();
    }

    public static function create(int $instrumentId, array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (instrument_id, txn_date, amount, txn_type, notes) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $instrumentId,
            $data['txn_date'],
            $data['amount'],
            $data['txn_type'],
            $data['notes'] ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id, int $instrumentId): void
    {
        Database::connection()
            ->prepare('DELETE FROM transactions WHERE id = ? AND instrument_id = ?')
            ->execute([$id, $instrumentId]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM transactions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
