-- Audit log: trace inserts / updates / deletes on the legal + financial tables
-- so that "lost data" (e.g. cascade deletes) can be traced and reconstructed.
-- Lives inside dbrb_2026 — no separate database required.
-- Run via the mysql CLI (it understands DELIMITER):
--     mysql -u root dbrb_2026 < migrations/add_audit_log.sql
--
-- Actor: the application should set the acting user id per request BEFORE any
-- write, e.g.  SET @app_user_id = <current user id>;
-- The triggers read @app_user_id (NULL if not set). No FK on actor_user_id so
-- the audit trail survives even if that user is later deleted.

-- 1. The audit table ---------------------------------------------------------
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

-- 2. Triggers ----------------------------------------------------------------
-- NOTE: JSON_OBJECT lists a curated set of key columns per table; extend it
-- with more columns if you need a fuller snapshot.

DELIMITER $$

-- ---- contracts ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_contracts_ai $$
CREATE TRIGGER trg_contracts_ai AFTER INSERT ON contracts FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, new_values)
    VALUES ('contracts', NEW.id, 'insert', @app_user_id,
        JSON_OBJECT('contract_code', NEW.contract_code, 'tenancy_id', NEW.tenancy_id,
                    'student_id', NEW.student_id, 'landlord_id', NEW.landlord_id,
                    'agent_id', NEW.agent_id, 'property_id', NEW.property_id,
                    'status', NEW.status, 'monthly_rent', NEW.monthly_rent,
                    'deposit', NEW.deposit, 'activated_at', NEW.activated_at));
END $$

DROP TRIGGER IF EXISTS trg_contracts_au $$
CREATE TRIGGER trg_contracts_au AFTER UPDATE ON contracts FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values, new_values)
    VALUES ('contracts', NEW.id, 'update', @app_user_id,
        JSON_OBJECT('status', OLD.status, 'monthly_rent', OLD.monthly_rent,
                    'deposit', OLD.deposit, 'activated_at', OLD.activated_at),
        JSON_OBJECT('status', NEW.status, 'monthly_rent', NEW.monthly_rent,
                    'deposit', NEW.deposit, 'activated_at', NEW.activated_at));
END $$

DROP TRIGGER IF EXISTS trg_contracts_ad $$
CREATE TRIGGER trg_contracts_ad AFTER DELETE ON contracts FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values)
    VALUES ('contracts', OLD.id, 'delete', @app_user_id,
        JSON_OBJECT('contract_code', OLD.contract_code, 'tenancy_id', OLD.tenancy_id,
                    'student_id', OLD.student_id, 'landlord_id', OLD.landlord_id,
                    'agent_id', OLD.agent_id, 'property_id', OLD.property_id,
                    'status', OLD.status, 'monthly_rent', OLD.monthly_rent,
                    'deposit', OLD.deposit, 'activated_at', OLD.activated_at));
END $$

-- ---- tenancies ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_tenancies_ai $$
CREATE TRIGGER trg_tenancies_ai AFTER INSERT ON tenancies FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, new_values)
    VALUES ('tenancies', NEW.id, 'insert', @app_user_id,
        JSON_OBJECT('student_id', NEW.student_id, 'property_id', NEW.property_id,
                    'landlord_id', NEW.landlord_id, 'agent_id', NEW.agent_id,
                    'status', NEW.status, 'start_date', NEW.start_date,
                    'end_date', NEW.end_date, 'duration_type', NEW.duration_type,
                    'monthly_rent', NEW.monthly_rent, 'deposit', NEW.deposit));
END $$

DROP TRIGGER IF EXISTS trg_tenancies_au $$
CREATE TRIGGER trg_tenancies_au AFTER UPDATE ON tenancies FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values, new_values)
    VALUES ('tenancies', NEW.id, 'update', @app_user_id,
        JSON_OBJECT('status', OLD.status, 'agent_id', OLD.agent_id,
                    'start_date', OLD.start_date, 'end_date', OLD.end_date,
                    'monthly_rent', OLD.monthly_rent, 'deposit', OLD.deposit),
        JSON_OBJECT('status', NEW.status, 'agent_id', NEW.agent_id,
                    'start_date', NEW.start_date, 'end_date', NEW.end_date,
                    'monthly_rent', NEW.monthly_rent, 'deposit', NEW.deposit));
END $$

DROP TRIGGER IF EXISTS trg_tenancies_ad $$
CREATE TRIGGER trg_tenancies_ad AFTER DELETE ON tenancies FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values)
    VALUES ('tenancies', OLD.id, 'delete', @app_user_id,
        JSON_OBJECT('student_id', OLD.student_id, 'property_id', OLD.property_id,
                    'landlord_id', OLD.landlord_id, 'agent_id', OLD.agent_id,
                    'status', OLD.status, 'start_date', OLD.start_date,
                    'end_date', OLD.end_date, 'duration_type', OLD.duration_type,
                    'monthly_rent', OLD.monthly_rent, 'deposit', OLD.deposit));
END $$

-- ---- agent_commissions ----------------------------------------------------
DROP TRIGGER IF EXISTS trg_agent_commissions_ai $$
CREATE TRIGGER trg_agent_commissions_ai AFTER INSERT ON agent_commissions FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, new_values)
    VALUES ('agent_commissions', NEW.id, 'insert', @app_user_id,
        JSON_OBJECT('contract_id', NEW.contract_id, 'agent_id', NEW.agent_id,
                    'base_rent', NEW.base_rent, 'commission_amt', NEW.commission_amt,
                    'sst_amt', NEW.sst_amt, 'total_payable', NEW.total_payable,
                    'status', NEW.status, 'earned_at', NEW.earned_at,
                    'released_at', NEW.released_at, 'paid_at', NEW.paid_at));
END $$

DROP TRIGGER IF EXISTS trg_agent_commissions_au $$
CREATE TRIGGER trg_agent_commissions_au AFTER UPDATE ON agent_commissions FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values, new_values)
    VALUES ('agent_commissions', NEW.id, 'update', @app_user_id,
        JSON_OBJECT('status', OLD.status, 'total_payable', OLD.total_payable,
                    'released_at', OLD.released_at, 'paid_at', OLD.paid_at,
                    'payment_ref', OLD.payment_ref),
        JSON_OBJECT('status', NEW.status, 'total_payable', NEW.total_payable,
                    'released_at', NEW.released_at, 'paid_at', NEW.paid_at,
                    'payment_ref', NEW.payment_ref));
END $$

DROP TRIGGER IF EXISTS trg_agent_commissions_ad $$
CREATE TRIGGER trg_agent_commissions_ad AFTER DELETE ON agent_commissions FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values)
    VALUES ('agent_commissions', OLD.id, 'delete', @app_user_id,
        JSON_OBJECT('contract_id', OLD.contract_id, 'agent_id', OLD.agent_id,
                    'base_rent', OLD.base_rent, 'commission_amt', OLD.commission_amt,
                    'sst_amt', OLD.sst_amt, 'total_payable', OLD.total_payable,
                    'status', OLD.status, 'earned_at', OLD.earned_at,
                    'released_at', OLD.released_at, 'paid_at', OLD.paid_at));
END $$

DELIMITER ;

-- Usage reminder: set the actor once per request before writes, e.g.
--   $pdo->exec('SET @app_user_id = ' . (int)current_user_id());
