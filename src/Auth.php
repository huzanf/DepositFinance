<?php

namespace DepositFinance;

/**
 * Email + one-time-code login, no password. Also enforces one active session
 * per account: every successful OTP verification issues a fresh session_token,
 * stored both in the PHP session and on the user's row — check() compares the
 * two on every request, so logging in elsewhere immediately ends this session
 * the next time it's checked.
 */
class Auth
{
    private const OTP_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;
    /** Auto-logout after this many seconds with no request/heartbeat. Keep in sync with the ping interval in partials/header.php. */
    public const SESSION_IDLE_SECONDS = 1800;

    public static function bootstrap(): void
    {
        // Nothing to do yet; placeholder for future session hardening hooks.
    }

    /** True if $email belongs to an active account. */
    public static function emailExists(string $email): bool
    {
        return User::findActiveByEmail($email) !== null;
    }

    /**
     * Generates and stores a fresh OTP for an active account, respecting a resend
     * cooldown. Returns the plaintext code on success, null if the email doesn't
     * match an active account or a code was already sent recently.
     */
    public static function issueOtp(string $email): ?string
    {
        $user = User::findActiveByEmail($email);
        if (!$user) {
            return null;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT created_at FROM login_otps WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$user['id']]);
        $last = $stmt->fetchColumn();

        if ($last !== false && (time() - strtotime($last)) < self::RESEND_COOLDOWN_SECONDS) {
            AuditLog::record('otp_rate_limited', (int) $user['id'], $email);
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + self::OTP_TTL_MINUTES * 60);

        $stmt = $pdo->prepare(
            'INSERT INTO login_otps (user_id, otp_hash, expires_at, requested_ip) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT), $expiresAt, AuditLog::clientIp()]);

        AuditLog::record('otp_requested', (int) $user['id'], $email);

        return $code;
    }

    /**
     * @return string 'ok' | 'invalid' | 'expired' | 'too_many_attempts'
     */
    public static function verifyOtp(string $email, string $code): string
    {
        $user = User::findActiveByEmail($email);
        if (!$user) {
            return 'invalid';
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT * FROM login_otps WHERE user_id = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$user['id']]);
        $otp = $stmt->fetch();

        if (!$otp) {
            AuditLog::record('otp_failed', (int) $user['id'], $email);
            return 'invalid';
        }

        if ((int) $otp['attempts'] >= self::MAX_ATTEMPTS) {
            AuditLog::record('otp_failed', (int) $user['id'], $email);
            return 'too_many_attempts';
        }

        if (strtotime($otp['expires_at']) < time()) {
            AuditLog::record('otp_expired', (int) $user['id'], $email);
            return 'expired';
        }

        if (!password_verify($code, $otp['otp_hash'])) {
            $pdo->prepare('UPDATE login_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$otp['id']]);
            AuditLog::record('otp_failed', (int) $user['id'], $email);
            return 'invalid';
        }

        $pdo->prepare('UPDATE login_otps SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$otp['id']]);

        self::login($user);
        AuditLog::record('otp_verified', (int) $user['id'], $email);

        return 'ok';
    }

    private static function login(array $user): void
    {
        // Rotate the session ID (prevents session fixation) and issue a fresh
        // token that invalidates any other active session for this account.
        session_regenerate_id(true);
        $sessionToken = bin2hex(random_bytes(32));

        User::setSessionToken((int) $user['id'], $sessionToken, AuditLog::clientIp());

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['last_activity'] = time();
    }

    /**
     * True only if this browser's session is BOTH authenticated and still the
     * most recent login for this account — a newer login elsewhere clears this.
     */
    public static function check(): bool
    {
        if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) {
            return false;
        }

        static $verified = null;
        if ($verified !== null) {
            return $verified;
        }

        $idleSeconds = time() - (int) ($_SESSION['last_activity'] ?? time());
        if ($idleSeconds > self::SESSION_IDLE_SECONDS) {
            AuditLog::record('session_timeout', (int) $_SESSION['user_id'], $_SESSION['user_name'] ?? null);
            self::logout();
            return $verified = false;
        }

        $currentToken = User::currentSessionToken((int) $_SESSION['user_id']);

        if ($currentToken === null || !hash_equals($currentToken, $_SESSION['session_token'])) {
            AuditLog::record('session_replaced', (int) $_SESSION['user_id'], $_SESSION['user_name'] ?? null);
            self::logout();
            return $verified = false;
        }

        $_SESSION['last_activity'] = time();
        return $verified = true;
    }

    public static function isLoggedIn(): bool
    {
        return self::check();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login.php');
        }
    }

    public static function isAdmin(): bool
    {
        return self::check() && ($_SESSION['user_role'] ?? null) === 'admin';
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('You need administrator access to view this page.');
        }
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            AuditLog::record('logout', (int) $_SESSION['user_id'], $_SESSION['user_name'] ?? null);
        }
        $_SESSION = [];
        session_destroy();
    }
}
