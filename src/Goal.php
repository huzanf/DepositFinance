<?php

namespace DepositFinance;

class Goal
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM goals ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM goals WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO goals (name, target_amount, notes) VALUES (?, ?, ?)');
        $stmt->execute([
            $data['name'],
            $data['target_amount'] !== '' ? $data['target_amount'] : null,
            $data['notes'] ?: null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE goals SET name = ?, target_amount = ?, notes = ? WHERE id = ?');
        $stmt->execute([
            $data['name'],
            $data['target_amount'] !== '' ? $data['target_amount'] : null,
            $data['notes'] ?: null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        // instruments.goal_id is ON DELETE SET NULL, so their history is kept.
        Database::connection()->prepare('DELETE FROM goals WHERE id = ?')->execute([$id]);
    }
}
