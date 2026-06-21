-- Migration: Add inspection scheduling support for property listing verification
-- Run once against the rentbridge database

-- 1. Track when property listing inspection was completed (before approve/reject)
ALTER TABLE properties
    ADD COLUMN IF NOT EXISTS inspection_completed_at DATETIME NULL AFTER agent_status;

-- 2. Track the confirmed inspection schedule on properties
ALTER TABLE properties
    ADD COLUMN IF NOT EXISTS inspection_scheduled_at DATETIME NULL AFTER inspection_completed_at,
    ADD COLUMN IF NOT EXISTS inspection_access_method VARCHAR(50) NULL AFTER inspection_scheduled_at,
    ADD COLUMN IF NOT EXISTS inspection_access_detail TEXT NULL AFTER inspection_access_method;

-- 3. Allow agent_status ENUM to include 'inspecting' intermediate state
ALTER TABLE properties
    MODIFY COLUMN agent_status ENUM('pending','inspecting','accepted','rejected','timeout') NULL;
