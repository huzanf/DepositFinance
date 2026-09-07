<?php

namespace DepositFinance;

class Member
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM members ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO members (name, notes) VALUES (?, ?)');
        $stmt->execute([$data['name'], $data['notes'] ?: null]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE members SET name = ?, notes = ? WHERE id = ?');
        $stmt->execute([$data['name'], $data['notes'] ?: null, $id]);
    }

    public static function delete(int $id): void
    {
        // instruments.member_id is ON DELETE SET NULL, so their history is kept.
        Database::connection()->prepare('DELETE FROM members WHERE id = ?')->execute([$id]);
    }
}
