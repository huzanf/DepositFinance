<?php

namespace DepositFinance;

class Instrument
{
    public const TYPES = ['FD', 'RD', 'SIP'];
    public const STATUSES = ['active', 'matured', 'closed'];
    public const FREQUENCIES = ['monthly', 'quarterly', 'yearly', 'one-time'];
    public const COMPOUNDING = ['monthly', 'quarterly', 'half-yearly', 'yearly'];

    public static function all(?string $type = null, ?string $status = null): array
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

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO instruments
                (type, name, institution, start_date, maturity_date, interest_rate, compounding_frequency,
                 principal_amount, installment_amount, frequency, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['type'],
            $data['name'],
            $data['institution'],
            $data['start_date'],
            $data['maturity_date'] ?: null,
            $data['interest_rate'] !== '' ? $data['interest_rate'] : null,
            $data['compounding_frequency'] ?: null,
            $data['principal_amount'] !== '' ? $data['principal_amount'] : null,
            $data['installment_amount'] !== '' ? $data['installment_amount'] : null,
            $data['frequency'],
            $data['status'],
            $data['notes'] ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE instruments SET
                type = ?, name = ?, institution = ?, start_date = ?, maturity_date = ?,
                interest_rate = ?, compounding_frequency = ?, principal_amount = ?,
                installment_amount = ?, frequency = ?, status = ?, notes = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['type'],
            $data['name'],
            $data['institution'],
            $data['start_date'],
            $data['maturity_date'] ?: null,
            $data['interest_rate'] !== '' ? $data['interest_rate'] : null,
            $data['compounding_frequency'] ?: null,
            $data['principal_amount'] !== '' ? $data['principal_amount'] : null,
            $data['installment_amount'] !== '' ? $data['installment_amount'] : null,
            $data['frequency'],
            $data['status'],
            $data['notes'] ?: null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM instruments WHERE id = ?')->execute([$id]);
    }
}
