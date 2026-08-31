-- In-system signing for co-tenants without an account.
-- A per-co-tenant secure token lets an account-less co-tenant sign the contract
-- via a link (no login), so every party signs digitally and all signatures
-- merge into one contract PDF. Run against dbrb_2026. Safe to re-run.

ALTER TABLE co_tenants
    ADD COLUMN IF NOT EXISTS sign_token VARCHAR(64) DEFAULT NULL
        COMMENT 'secure token for link-based signing (account-less co-tenants)';

ALTER TABLE co_tenants
    ADD INDEX IF NOT EXISTS idx_sign_token (sign_token);
