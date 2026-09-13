<?php

namespace DepositFinance;

class User
{
    public const ROLES = ['admin', 'member'];

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM users ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findActiveByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([trim(strtolower($email))]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Creates a new user, or reactivates/updates an existing one with the same
     * email (so re-adding someone who was deactivated just brings them back).
     */
    public static function createOrUpdate(string $name, string $email, string $role): void
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'member';
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, role, is_active) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), is_active = 1'
        );
        $stmt->execute([trim($name), trim(strtolower($email)), $role]);
    }

    public static function toggleActive(int $id): void
    {
        Database::connection()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    }

    public static function setSessionToken(int $id, string $token, ?string $ip): void
    {
        Database::connection()
            ->prepare('UPDATE users SET current_session_token = ?, last_login_at = CURRENT_TIMESTAMP, last_login_ip = ? WHERE id = ?')
            ->execute([$token, $ip, $id]);
    }

    public static function currentSessionToken(int $id): ?string
    {
        $stmt = Database::connection()->prepare('SELECT current_session_token FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        $token = $stmt->fetchColumn();
        return $token === false ? null : $token;
    }
}
