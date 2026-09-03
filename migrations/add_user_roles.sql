-- Multi-role support: user_roles junction table
-- Turns the disjoint single-role model (users.primary_role) into an
-- overlapping model where one user can hold several roles at once
-- (e.g. a student who is also a landlord).
-- Run against dbrb_2026. Safe to re-run (idempotent).

-- 1. Junction table: (user_id, role) — the explicit overlapping M:M.
CREATE TABLE IF NOT EXISTS user_roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    role        ENUM('student','landlord','agent','admin') NOT NULL,
    is_primary  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'default role at login / when none is active',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_role (user_id, role),
    KEY idx_role (role),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Backfill from the current single primary_role (marked as primary).
INSERT IGNORE INTO user_roles (user_id, role, is_primary, created_at)
SELECT id, primary_role, 1, created_at
  FROM users;

-- 3. Backfill overlapping roles that already exist as subtype profile rows
--    (in case any user_id has more than one profile). Non-primary.
INSERT IGNORE INTO user_roles (user_id, role, is_primary)
SELECT user_id, 'student',  0 FROM students;
INSERT IGNORE INTO user_roles (user_id, role, is_primary)
SELECT user_id, 'landlord', 0 FROM landlords;
INSERT IGNORE INTO user_roles (user_id, role, is_primary)
SELECT user_id, 'agent',    0 FROM agents;

-- NOTE: `users.primary_role` and `users.last_used_role` are retained for
-- backward compatibility. Application code should migrate authorization to
-- read from user_roles (e.g. "does the user have role X?") rather than
-- comparing primary_role directly. See TODO.md (Multi-role support).
