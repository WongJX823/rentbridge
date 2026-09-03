-- Data protection: soft-delete columns + stop cascade deletes from wiping
-- legal/financial records.
--
-- Problem: contracts, tenancies, and agent_commissions currently sit behind
-- ON DELETE CASCADE chains from users and properties. Deleting one `users`
-- row (or one `properties` row) silently wipes that person's tenancies,
-- contracts, and commission records — with no trace beyond the audit_log
-- triggers (see migrations/add_audit_log.sql).
--
-- Fix, in two parts:
--   1. Add deleted_at to all three tables so they CAN be hidden without a
--      hard DELETE (no code writes to it yet — this lays the column down
--      for whenever a "delete" UI is built; existing queries are unaffected
--      until then).
--   2. Change every CASCADE delete rule that points at contracts, tenancies,
--      or agent_commissions to RESTRICT, so deleting a user or a property
--      is simply blocked (FK violation) while any of these records still
--      reference it, instead of silently cascading through them.
--
-- Left alone on purpose: tenancies.agent_id and tenancies.cancelled_by
-- already use ON DELETE SET NULL — losing the agent reference doesn't
-- destroy the tenancy record itself, so RESTRICT would be stricter than
-- needed there.
--
-- Run against dbrb_2026. Safe to re-run.
--     mysql -u root dbrb_2026 < migrations/add_soft_delete_and_restrict_cascade.sql

-- 1. Soft-delete columns -----------------------------------------------------
ALTER TABLE contracts
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'soft delete — filter WHERE deleted_at IS NULL; never hard DELETE';

ALTER TABLE tenancies
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'soft delete — filter WHERE deleted_at IS NULL; never hard DELETE';

ALTER TABLE agent_commissions
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'soft delete — filter WHERE deleted_at IS NULL; never hard DELETE';

-- 2. CASCADE -> RESTRICT ------------------------------------------------------
-- MySQL has no "DROP FOREIGN KEY IF EXISTS" / "ADD CONSTRAINT IF NOT EXISTS",
-- so this drops+recreates each constraint inside a small idempotent
-- procedure: skip the drop if the constraint is already gone (e.g. this
-- migration already ran), then always (re)add it as RESTRICT.

DELIMITER $$

DROP PROCEDURE IF EXISTS _rb_restrict_fk $$
CREATE PROCEDURE _rb_restrict_fk(
    IN p_table   VARCHAR(64),
    IN p_constraint VARCHAR(64),
    IN p_column  VARCHAR(64),
    IN p_ref_table VARCHAR(64),
    IN p_ref_column VARCHAR(64)
)
BEGIN
    DECLARE existing INT DEFAULT 0;

    SELECT COUNT(*) INTO existing
      FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = p_table
       AND CONSTRAINT_NAME = p_constraint
       AND CONSTRAINT_TYPE = 'FOREIGN KEY';

    IF existing > 0 THEN
        SET @drop_sql = CONCAT('ALTER TABLE ', p_table, ' DROP FOREIGN KEY ', p_constraint);
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

    SET @add_sql = CONCAT(
        'ALTER TABLE ', p_table,
        ' ADD CONSTRAINT ', p_constraint,
        ' FOREIGN KEY (', p_column, ')',
        ' REFERENCES ', p_ref_table, ' (', p_ref_column, ')',
        ' ON DELETE RESTRICT ON UPDATE RESTRICT'
    );
    PREPARE stmt FROM @add_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END $$

DELIMITER ;

-- tenancies: protect against a deleted user or property wiping the tenancy
CALL _rb_restrict_fk('tenancies', 'tenancies_ibfk_1', 'student_id',  'users',      'id');
CALL _rb_restrict_fk('tenancies', 'tenancies_ibfk_3', 'landlord_id', 'users',      'id');
CALL _rb_restrict_fk('tenancies', 'tenancies_ibfk_2', 'property_id', 'properties', 'id');

-- contracts: protect against a deleted user, property, or tenancy wiping the contract
CALL _rb_restrict_fk('contracts', 'contracts_ibfk_2', 'student_id',  'users',      'id');
CALL _rb_restrict_fk('contracts', 'contracts_ibfk_3', 'landlord_id', 'users',      'id');
CALL _rb_restrict_fk('contracts', 'contracts_ibfk_4', 'agent_id',    'users',      'id');
CALL _rb_restrict_fk('contracts', 'contracts_ibfk_5', 'property_id', 'properties', 'id');
CALL _rb_restrict_fk('contracts', 'contracts_ibfk_1', 'tenancy_id',  'tenancies',  'id');

-- agent_commissions: protect against a deleted agent or contract wiping the commission record
CALL _rb_restrict_fk('agent_commissions', 'agent_commissions_ibfk_2', 'agent_id',    'users',     'id');
CALL _rb_restrict_fk('agent_commissions', 'agent_commissions_ibfk_1', 'contract_id', 'contracts', 'id');

DROP PROCEDURE IF EXISTS _rb_restrict_fk;
