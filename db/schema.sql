-- DepositFinance schema
-- Import this via phpMyAdmin (or `mysql depositfinance < db/schema.sql`)
-- after creating an empty database named to match config/config.php.
--
-- Upgrading an existing install? Run db/migrate_multiuser.sql instead —
-- this file is for a brand new, empty database only.

-- Portal login accounts (not to be confused with `members`, which just tags
-- who an instrument belongs to and has no login of its own). Login is email +
-- a one-time code, so there's no password column at all.
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    current_session_token VARCHAR(64) NULL COMMENT 'Rotated on every login; check() compares this to the session, so a newer login elsewhere ends this one',
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-time login codes, scoped to a user rather than a raw email string.
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

-- Login activity trail: code requests, verifications, session takeovers,
-- idle timeouts, logouts. Viewable on login_audit.php (admin only).
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

CREATE TABLE IF NOT EXISTS members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(14,2) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS instruments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('FD', 'RD', 'SIP') NOT NULL,
    name VARCHAR(150) NOT NULL,
    institution VARCHAR(150) NOT NULL,
    account_number VARCHAR(100) NULL COMMENT 'Bank/folio-issued RD, FD or SIP account number',
    member_id INT UNSIGNED NULL COMMENT 'Which family member this belongs to',
    goal_id INT UNSIGNED NULL COMMENT 'What this deposit is earmarked for',
    start_date DATE NOT NULL,
    maturity_date DATE NULL,
    interest_rate DECIMAL(5,2) NULL COMMENT 'Annual %, for FD/RD. Optional expected return for SIP.',
    compounding_frequency ENUM('monthly', 'quarterly', 'half-yearly', 'yearly') NULL DEFAULT 'quarterly',
    principal_amount DECIMAL(14,2) NULL COMMENT 'Lump sum, for FD',
    installment_amount DECIMAL(14,2) NULL COMMENT 'Recurring amount, for RD/SIP',
    frequency ENUM('monthly', 'quarterly', 'yearly', 'one-time') NOT NULL DEFAULT 'monthly',
    tenure_months INT UNSIGNED NULL,
    maturity_amount DECIMAL(14,2) NULL COMMENT 'Expected/actual payout at maturity, as stated by the bank',
    status ENUM('active', 'matured', 'renewed', 'closed') NOT NULL DEFAULT 'active',
    renewed_from_id INT UNSIGNED NULL COMMENT 'Predecessor instrument this one renews, if any',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE SET NULL,
    FOREIGN KEY (renewed_from_id) REFERENCES instruments(id) ON DELETE SET NULL,
    INDEX idx_instruments_member (member_id),
    INDEX idx_instruments_goal (goal_id),
    INDEX idx_instruments_renewed_from (renewed_from_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instrument_id INT UNSIGNED NOT NULL,
    txn_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    txn_type ENUM('contribution', 'withdrawal', 'maturity_payout', 'interest_credit') NOT NULL DEFAULT 'contribution',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE,
    INDEX idx_transactions_instrument (instrument_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS valuations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instrument_id INT UNSIGNED NOT NULL,
    value_date DATE NOT NULL,
    current_value DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE,
    INDEX idx_valuations_instrument (instrument_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reminders_sent (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instrument_id INT UNSIGNED NOT NULL,
    threshold_days INT NOT NULL COMMENT '60, 30, 7 or 0 (0 = due on maturity day)',
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_instrument_threshold (instrument_id, threshold_days)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
