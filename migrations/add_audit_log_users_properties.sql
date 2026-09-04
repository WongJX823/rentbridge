-- Extend audit_log coverage (see migrations/add_audit_log.sql) to `users` and
-- `properties` — the two tables TODO.md flagged as still uncovered.
-- Same actor convention: SET @app_user_id = <id> before writes; triggers read
-- it (NULL if not set). Run via the mysql CLI (it understands DELIMITER):
--     mysql -u root dbrb_2026 < migrations/add_audit_log_users_properties.sql
--
-- users.password_hash is deliberately excluded from every snapshot — even
-- hashed, it doesn't belong in a table other actors can read.

DELIMITER $$

-- ---- users ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_users_ai $$
CREATE TRIGGER trg_users_ai AFTER INSERT ON users FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, new_values)
    VALUES ('users', NEW.id, 'insert', @app_user_id,
        JSON_OBJECT('email', NEW.email, 'primary_role', NEW.primary_role,
                    'status', NEW.status, 'last_used_role', NEW.last_used_role));
END $$

DROP TRIGGER IF EXISTS trg_users_au $$
CREATE TRIGGER trg_users_au AFTER UPDATE ON users FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values, new_values)
    VALUES ('users', NEW.id, 'update', @app_user_id,
        JSON_OBJECT('email', OLD.email, 'primary_role', OLD.primary_role,
                    'status', OLD.status, 'last_used_role', OLD.last_used_role),
        JSON_OBJECT('email', NEW.email, 'primary_role', NEW.primary_role,
                    'status', NEW.status, 'last_used_role', NEW.last_used_role));
END $$

DROP TRIGGER IF EXISTS trg_users_ad $$
CREATE TRIGGER trg_users_ad AFTER DELETE ON users FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values)
    VALUES ('users', OLD.id, 'delete', @app_user_id,
        JSON_OBJECT('email', OLD.email, 'primary_role', OLD.primary_role,
                    'status', OLD.status, 'last_used_role', OLD.last_used_role));
END $$

-- ---- properties ---------------------------------------------------------
DROP TRIGGER IF EXISTS trg_properties_ai $$
CREATE TRIGGER trg_properties_ai AFTER INSERT ON properties FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, new_values)
    VALUES ('properties', NEW.id, 'insert', @app_user_id,
        JSON_OBJECT('landlord_id', NEW.landlord_id, 'title', NEW.title,
                    'property_type', NEW.property_type, 'monthly_rent', NEW.monthly_rent,
                    'deposit', NEW.deposit, 'status', NEW.status,
                    'assigned_agent_id', NEW.assigned_agent_id, 'agent_status', NEW.agent_status));
END $$

DROP TRIGGER IF EXISTS trg_properties_au $$
CREATE TRIGGER trg_properties_au AFTER UPDATE ON properties FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values, new_values)
    VALUES ('properties', NEW.id, 'update', @app_user_id,
        JSON_OBJECT('title', OLD.title, 'monthly_rent', OLD.monthly_rent,
                    'deposit', OLD.deposit, 'status', OLD.status,
                    'assigned_agent_id', OLD.assigned_agent_id, 'agent_status', OLD.agent_status),
        JSON_OBJECT('title', NEW.title, 'monthly_rent', NEW.monthly_rent,
                    'deposit', NEW.deposit, 'status', NEW.status,
                    'assigned_agent_id', NEW.assigned_agent_id, 'agent_status', NEW.agent_status));
END $$

DROP TRIGGER IF EXISTS trg_properties_ad $$
CREATE TRIGGER trg_properties_ad AFTER DELETE ON properties FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, row_id, action, actor_user_id, old_values)
    VALUES ('properties', OLD.id, 'delete', @app_user_id,
        JSON_OBJECT('landlord_id', OLD.landlord_id, 'title', OLD.title,
                    'property_type', OLD.property_type, 'monthly_rent', OLD.monthly_rent,
                    'deposit', OLD.deposit, 'status', OLD.status,
                    'assigned_agent_id', OLD.assigned_agent_id, 'agent_status', OLD.agent_status));
END $$

DELIMITER ;
