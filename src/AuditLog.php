<?php

namespace DepositFinance;

class AuditLog
{
    public static function record(string $event, ?int $userId, ?string $email): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO login_audit (user_id, email, event, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $email,
            $event,
            self::clientIp(),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    public static function clientIp(): ?string
    {
        // Behind Cloudflare or another reverse proxy, REMOTE_ADDR is the proxy's
        // own address — these headers (checked in order of trustworthiness for
        // a typical shared-hosting setup) carry the real client IP instead.
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;
    }
}
