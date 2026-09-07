<?php

namespace DepositFinance;

class Auth
{
    private const OTP_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;

    /** Called once per request, right after the session starts. */
    public static function bootstrap(): void
    {
        // Nothing to do yet; placeholder for future session hardening hooks.
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_email']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect('/login.php');
        }
    }

    public static function ownerEmail(): string
    {
        return strtolower(trim(config('owner_email')));
    }

    public static function isOwnerEmail(string $email): bool
    {
        return strtolower(trim($email)) === self::ownerEmail();
    }

    /**
     * Generates and stores a fresh OTP for the owner email, respecting a resend cooldown.
     * Returns the plaintext code on success, or null if still within the cooldown window.
     */
    public static function issueOtp(string $email): ?string
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT created_at FROM otp_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$email]);
        $last = $stmt->fetchColumn();

        if ($last !== false) {
            $secondsSince = time() - strtotime($last);
            if ($secondsSince < self::RESEND_COOLDOWN_SECONDS) {
                return null;
            }
        }

        $pdo->prepare('DELETE FROM otp_codes WHERE email = ?')->execute([$email]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + self::OTP_TTL_MINUTES * 60);

        $stmt = $pdo->prepare(
            'INSERT INTO otp_codes (email, code_hash, attempts, expires_at) VALUES (?, ?, 0, ?)'
        );
        $stmt->execute([$email, password_hash($code, PASSWORD_DEFAULT), $expiresAt]);

        return $code;
    }

    /**
     * @return string 'ok' | 'invalid' | 'expired' | 'too_many_attempts'
     */
    public static function verifyOtp(string $email, string $code): string
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM otp_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row) {
            return 'invalid';
        }

        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return 'too_many_attempts';
        }

        if (strtotime($row['expires_at']) < time()) {
            return 'expired';
        }

        if (!password_verify($code, $row['code_hash'])) {
            $pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
            return 'invalid';
        }

        $pdo->prepare('DELETE FROM otp_codes WHERE email = ?')->execute([$email]);
        self::login($email);

        return 'ok';
    }

    private static function login(string $email): void
    {
        session_regenerate_id(true);
        $_SESSION['user_email'] = $email;
        $_SESSION['logged_in_at'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
