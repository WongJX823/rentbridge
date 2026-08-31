-- Academic calendar terms — official UTeM semester start/end dates.
-- Populated by the admin importer (admin/academic_calendar.php) after the
-- admin reviews the GPT-4o-extracted dates. Used to resolve tenancy durations
-- to real calendar dates. Run against dbrb_2026. Safe to re-run.

CREATE TABLE IF NOT EXISTS academic_terms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session     VARCHAR(20) NOT NULL COMMENT 'e.g. 2025/2026',
    term        ENUM('sem1','sem2','short') NOT NULL,
    label       VARCHAR(60) NOT NULL COMMENT 'e.g. Semester 1',
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_term (session, term),
    KEY idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
