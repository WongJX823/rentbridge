-- Per-party choice between e-signing and physical ("manual") signing on the
-- same contract. agent/case.php already had a whole UI block for this
-- ("Tenant and Landlord chose to sign a physical copy") but it read
-- contracts.student_sign_method / contracts.landlord_sign_method, neither of
-- which ever existed as columns or were written anywhere — dead code, and
-- wrong shape besides (a contract can have several tenants, not one).
--
-- Adds the real per-party columns: one per co-tenant, one for the landlord
-- (singular per contract, so a single column is correct there).
--
-- Run against dbrb_2026. Safe to re-run.
--     mysql -u root dbrb_2026 < migrations/add_mixed_signing_method.sql

ALTER TABLE co_tenants
    ADD COLUMN IF NOT EXISTS sign_method ENUM('esign','manual') NOT NULL DEFAULT 'esign'
        COMMENT 'per-tenant choice, set when it is their turn to sign'
        AFTER sign_order;

ALTER TABLE contracts
    ADD COLUMN IF NOT EXISTS landlord_sign_method ENUM('esign','manual') NOT NULL DEFAULT 'esign'
        COMMENT 'landlord''s choice, set when it is their turn to sign'
        AFTER landlord_sign_ip;
