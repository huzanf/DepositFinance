-- DepositFinance schema
-- Import this via phpMyAdmin (or `mysql depositfinance < db/schema.sql`)
-- after creating an empty database named to match config/config.php.

CREATE TABLE IF NOT EXISTS instruments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('FD', 'RD', 'SIP') NOT NULL,
    name VARCHAR(150) NOT NULL,
    institution VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    maturity_date DATE NULL,
    interest_rate DECIMAL(5,2) NULL COMMENT 'Annual %, for FD/RD. Optional expected return for SIP.',
    compounding_frequency ENUM('monthly', 'quarterly', 'half-yearly', 'yearly') NULL DEFAULT 'quarterly',
    principal_amount DECIMAL(14,2) NULL COMMENT 'Lump sum, for FD',
    installment_amount DECIMAL(14,2) NULL COMMENT 'Recurring amount, for RD/SIP',
    frequency ENUM('monthly', 'quarterly', 'yearly', 'one-time') NOT NULL DEFAULT 'monthly',
    status ENUM('active', 'matured', 'closed') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

CREATE TABLE IF NOT EXISTS otp_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
