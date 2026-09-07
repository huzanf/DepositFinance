<?php

namespace DepositFinance;

class ValuationRepo
{
    public static function forInstrument(int $instrumentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM valuations WHERE instrument_id = ? ORDER BY value_date DESC, id DESC'
        );
        $stmt->execute([$instrumentId]);
        return $stmt->fetchAll();
    }

    public static function latestFor(int $instrumentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM valuations WHERE instrument_id = ? ORDER BY value_date DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$instrumentId]);
        return $stmt->fetch() ?: null;
    }

    /** Latest valuation per instrument, keyed by instrument_id. */
    public static function latestByInstrument(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query(
            'SELECT v.* FROM valuations v
             INNER JOIN (
                 SELECT instrument_id, MAX(value_date) AS max_date
                 FROM valuations GROUP BY instrument_id
             ) latest ON latest.instrument_id = v.instrument_id AND latest.max_date = v.value_date
             ORDER BY v.instrument_id, v.id DESC'
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            // Keep only the first (highest id) row per instrument in case of ties on value_date.
            if (!isset($result[$row['instrument_id']])) {
                $result[$row['instrument_id']] = $row;
            }
        }

        return $result;
    }

    public static function create(int $instrumentId, array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO valuations (instrument_id, value_date, current_value) VALUES (?, ?, ?)'
        );
        $stmt->execute([$instrumentId, $data['value_date'], $data['current_value']]);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id, int $instrumentId): void
    {
        Database::connection()
            ->prepare('DELETE FROM valuations WHERE id = ? AND instrument_id = ?')
            ->execute([$id, $instrumentId]);
    }
}
