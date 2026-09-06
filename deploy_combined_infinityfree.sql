-- Combined migrations for InfinityFree (or any host without TRIGGER /
-- CREATE PROCEDURE privilege on the DB user).
--
-- Run this AFTER importing db/dbrb_2026.sql and add_academic_terms.sql.
--
-- Uses plain `ADD COLUMN`/`ADD INDEX` (no `IF NOT EXISTS`) — that clause is
-- a MariaDB-only extension on ALTER TABLE and is a syntax error on real
-- MySQL (confirmed against Aiven's managed MySQL 8.4). Safe here since this
-- file is meant for a fresh import, never a repeat run against a database
-- that already has these columns.
--
-- Deliberately NOT included here (already baked into db/dbrb_2026.sql as of
-- the June 25 dump — re-running them would error, e.g. "Duplicate column"
-- or "Table 'bookings' doesn't exist"):
--   add_cotenancy_semesters.sql, add_housemate_applications.sql,
--   add_inspection_scheduling.sql, rename_bookings_to_tenancies.sql
--
-- Deliberately NOT included here (100% CREATE TRIGGER statements, and free
-- shared MySQL commonly denies the TRIGGER privilege to the DB user):
--   add_audit_log_users_properties.sql
--   (no app code reads audit_log — only triggers write to it — so skipping
--   this is safe; the audit trail for users/properties just stays empty)
--
-- add_audit_log.sql is trimmed to its CREATE TABLE only (same reason —
-- its trigger block would fail the same way).
--
-- add_soft_delete_and_restrict_cascade.sql is rewritten below to use
-- session-variable + PREPARE/EXECUTE dynamic SQL instead of CREATE
-- PROCEDURE (also commonly a denied privilege on free shared MySQL).
-- PREPARE/EXECUTE need no special privilege beyond running the underlying
-- ALTER TABLE, which this DB user already has (earlier ALTERs succeeded).
-- It also looks up each FK's *actual current name* via information_schema
-- rather than assuming tenancies_ibfk_1-style names, so it's safe even if
-- your host's import auto-renamed constraints.
--
-- Order matters below: add_race_preference depends on columns added by
-- add_gender_preference (gender_preference) and add_student_gender
-- (students.gender) via its `AFTER <col>` clauses.


-- ========================================================================
-- 1. add_user_roles.sql
-- ========================================================================
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

INSERT IGNORE INTO user_roles (user_id, role, is_primary, created_at)
SELECT id, primary_role, 1, created_at
  FROM users;

INSERT IGNORE INTO user_roles (user_id, role, is_primary)
SELECT user_id, 'student',  0 FROM students;
INSERT IGNORE INTO user_roles (user_id, role, is_primary)
SELECT user_id, 'landlord', 0 FROM landlords;
INSERT IGNORE INTO user_roles (user_id, role, is_primary)
SELECT user_id, 'agent',    0 FROM agents;


-- ========================================================================
-- 2. add_audit_log.sql — TABLE ONLY (trigger block skipped, see header)
-- ========================================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_name    VARCHAR(64) NOT NULL,
    row_id        INT NOT NULL,
    action        ENUM('insert','update','delete') NOT NULL,
    actor_user_id INT NULL COMMENT 'from @app_user_id; no FK so audit survives user deletion',
    old_values    LONGTEXT NULL CHECK (old_values IS NULL OR JSON_VALID(old_values)),
    new_values    LONGTEXT NULL CHECK (new_values IS NULL OR JSON_VALID(new_values)),
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_table_row (table_name, row_id),
    KEY idx_action (action),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================================
-- 3. add_cotenant_sign_token.sql
-- ========================================================================
ALTER TABLE co_tenants
    ADD COLUMN sign_token VARCHAR(64) DEFAULT NULL
        COMMENT 'secure token for link-based signing (account-less co-tenants)';

ALTER TABLE co_tenants
    ADD INDEX idx_sign_token (sign_token);


-- ========================================================================
-- 4. add_gender_preference.sql
-- ========================================================================
ALTER TABLE `properties`
    ADD COLUMN `gender_preference` ENUM('any','male','female') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred tenant gender for this listing'
        AFTER `viewing_mode`;

ALTER TABLE `co_tenancy_posts`
    ADD COLUMN `gender_preference` ENUM('any','male','female') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred housemate gender'
        AFTER `semesters_needed`;


-- ========================================================================
-- 5. add_student_gender.sql
-- ========================================================================
ALTER TABLE `students`
    ADD COLUMN `gender` ENUM('male','female') DEFAULT NULL
        COMMENT 'Student gender for housemate gender-matching'
        AFTER `phone`;


-- ========================================================================
-- 6. add_race_preference.sql
-- ========================================================================
ALTER TABLE `properties`
    ADD COLUMN `race_preference` ENUM('any','malay','chinese','indian','others') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred tenant race for this listing'
        AFTER `gender_preference`;

ALTER TABLE `co_tenancy_posts`
    ADD COLUMN `race_preference` ENUM('any','malay','chinese','indian','others') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred housemate race'
        AFTER `gender_preference`;

ALTER TABLE `students`
    ADD COLUMN `race` ENUM('malay','chinese','indian','others') DEFAULT NULL
        COMMENT 'Student race for housemate race-matching'
        AFTER `gender`;


-- ========================================================================
-- 7. add_soft_delete_and_restrict_cascade.sql — REWRITTEN (no CREATE PROCEDURE)
-- ========================================================================

-- 7a. Soft-delete columns
ALTER TABLE contracts
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'soft delete — filter WHERE deleted_at IS NULL; never hard DELETE';

ALTER TABLE tenancies
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'soft delete — filter WHERE deleted_at IS NULL; never hard DELETE';

ALTER TABLE agent_commissions
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'soft delete — filter WHERE deleted_at IS NULL; never hard DELETE';

-- 7b. CASCADE -> RESTRICT, one FK at a time. Each block looks up the FK's
-- real current name (whatever your host named it on import) and swaps it.

-- tenancies.student_id -> users.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenancies' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `tenancies` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `tenancies` ADD CONSTRAINT `fk_tenancies_student_id` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- tenancies.landlord_id -> users.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenancies' AND COLUMN_NAME='landlord_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `tenancies` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `tenancies` ADD CONSTRAINT `fk_tenancies_landlord_id` FOREIGN KEY (`landlord_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- tenancies.property_id -> properties.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenancies' AND COLUMN_NAME='property_id' AND REFERENCED_TABLE_NAME='properties' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `tenancies` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `tenancies` ADD CONSTRAINT `fk_tenancies_property_id` FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- contracts.student_id -> users.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `contracts` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_student_id` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- contracts.landlord_id -> users.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='landlord_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `contracts` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_landlord_id` FOREIGN KEY (`landlord_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- contracts.agent_id -> users.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='agent_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `contracts` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_agent_id` FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- contracts.property_id -> properties.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='property_id' AND REFERENCED_TABLE_NAME='properties' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `contracts` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_property_id` FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- contracts.tenancy_id -> tenancies.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='tenancy_id' AND REFERENCED_TABLE_NAME='tenancies' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `contracts` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_tenancy_id` FOREIGN KEY (`tenancy_id`) REFERENCES `tenancies`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- agent_commissions.agent_id -> users.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agent_commissions' AND COLUMN_NAME='agent_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `agent_commissions` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `agent_commissions` ADD CONSTRAINT `fk_agent_commissions_agent_id` FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- agent_commissions.contract_id -> contracts.id
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agent_commissions' AND COLUMN_NAME='contract_id' AND REFERENCED_TABLE_NAME='contracts' LIMIT 1);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE `agent_commissions` DROP FOREIGN KEY `', @fk, '`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `agent_commissions` ADD CONSTRAINT `fk_agent_commissions_contract_id` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;


-- ========================================================================
-- 8. add_login_attempts.sql
-- ========================================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(191) NOT NULL COMMENT 'lowercased email that was attempted',
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_identifier_time (identifier, attempted_at),
    KEY idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================================
-- 9. add_mixed_signing_method.sql
-- ========================================================================
ALTER TABLE co_tenants
    ADD COLUMN sign_method ENUM('esign','manual') NOT NULL DEFAULT 'esign'
        COMMENT 'per-tenant choice, set when it is their turn to sign'
        AFTER sign_order;

ALTER TABLE contracts
    ADD COLUMN landlord_sign_method ENUM('esign','manual') NOT NULL DEFAULT 'esign'
        COMMENT 'landlord''s choice, set when it is their turn to sign'
        AFTER landlord_sign_ip;


-- ========================================================================
-- 10. seed_academic_terms_placeholder.sql
-- ========================================================================
INSERT IGNORE INTO academic_terms (session, term, label, start_date, end_date) VALUES
  ('2026/2027', 'sem1',  'Semester 1', '2026-09-14', '2027-01-18'),
  ('2026/2027', 'sem2',  'Semester 2', '2027-02-15', '2027-06-21'),
  ('2026/2027', 'short', 'Short Semester', '2027-06-28', '2027-08-16'),
  ('2027/2028', 'sem1',  'Semester 1', '2027-09-13', '2028-01-17'),
  ('2027/2028', 'sem2',  'Semester 2', '2028-02-14', '2028-06-19');
