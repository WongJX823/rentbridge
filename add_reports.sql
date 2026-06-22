-- Migration: create reports table for Admin Report & Flag System
-- Run once against dbrb_2026

CREATE TABLE IF NOT EXISTS reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id      INT NOT NULL,
    reported_user_id INT NOT NULL,
    context_type     ENUM('booking','message','general') NOT NULL DEFAULT 'general',
    context_id       INT NULL,
    reason           ENUM('harassment','scam','fake_information','misconduct','fraud','other') NOT NULL,
    details          TEXT NULL,
    status           ENUM('pending','reviewed','dismissed','actioned') NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at      TIMESTAMP NULL,
    reviewed_by      INT NULL,
    CONSTRAINT fk_report_reporter  FOREIGN KEY (reporter_id)      REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_reported  FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
