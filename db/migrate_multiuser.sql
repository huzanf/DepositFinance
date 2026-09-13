-- Upgrades an EXISTING DepositFinance database (single-owner login) to the
-- multi-user login model. Run this once via phpMyAdmin on your live database.
-- Safe to run even if some of these objects already exist (guarded by IF NOT
-- EXISTS / OR REPLACE-equivalent checks).
--
-- After running this, log in with the email you enter below (the one that
-- was in config.php's old owner_email) — it's seeded as the first admin.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    current_session_token VARCHAR(64) NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_otps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    requested_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_login_otps_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    email VARCHAR(190) NULL,
    event ENUM('otp_requested', 'otp_rate_limited', 'otp_verified', 'otp_failed', 'otp_expired', 'session_replaced', 'session_timeout', 'logout') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_login_audit_user (user_id, created_at),
    INDEX idx_login_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The old single-owner OTP table is no longer used — OTP codes are short-lived
-- (10-minute expiry) so there's nothing worth preserving from it.
DROP TABLE IF EXISTS otp_codes;

-- Seed the account that was previously your only login (owner_email in
-- config.php) as the first admin. EDIT THE EMAIL/NAME BELOW if needed before
-- running, then use the Users page in the app to add everyone else.
INSERT INTO users (name, email, role, is_active)
VALUES ('Huzan', 'huzanforbes@gmail.com', 'admin', 1)
ON DUPLICATE KEY UPDATE role = 'admin', is_active = 1;
