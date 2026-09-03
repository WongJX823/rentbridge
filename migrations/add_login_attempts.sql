-- Login rate limiting: auth/login.php had no brute-force protection at all —
-- unlimited attempts against any account or from any IP. Adds a table to
-- track attempts and lock out both the targeted account and the source IP
-- after too many failures in a rolling window.
--
-- Run against dbrb_2026. Safe to re-run.
--     mysql -u root dbrb_2026 < migrations/add_login_attempts.sql

CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(191) NOT NULL COMMENT 'lowercased email that was attempted',
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_identifier_time (identifier, attempted_at),
    KEY idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
