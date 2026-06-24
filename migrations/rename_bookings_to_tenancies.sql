-- Migration: rename bookings table and booking_id columns to tenancies/tenancy_id
-- Run this once against dbrb_2026

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Rename the main table
RENAME TABLE `bookings` TO `tenancies`;

-- 2. Rename booking_id columns in dependent tables
ALTER TABLE `agent_verifications`       CHANGE `booking_id` `tenancy_id` int(11) NOT NULL;
ALTER TABLE `contracts`                 CHANGE `booking_id` `tenancy_id` int(11) NOT NULL;
ALTER TABLE `co_tenants`                CHANGE `booking_id` `tenancy_id` int(11) NOT NULL;
ALTER TABLE `conversations`             CHANGE `booking_id` `tenancy_id` int(11) DEFAULT NULL;

-- 3. Update conversations context_type enum to replace 'booking' with 'tenancy'
ALTER TABLE `conversations`
    MODIFY `context_type` ENUM(
        'property_inquiry','tenancy','friend','agent_case',
        'other','contract_prep','housemate_group'
    ) NOT NULL DEFAULT 'other';

-- 4. Update existing data rows
UPDATE `conversations`  SET `context_type` = 'tenancy'          WHERE `context_type` = 'booking';
UPDATE `notifications`  SET `type`         = 'tenancy_request'  WHERE `type`         = 'booking_request';
UPDATE `notifications`  SET `type`         = 'tenancy_pending'  WHERE `type`         = 'booking_pending';
UPDATE `notifications`  SET `type`         = 'tenancy_cancelled' WHERE `type`        = 'booking_cancelled';

-- 5. Rename properties.status enum value 'booked' -> 'reserved'
ALTER TABLE `properties`
    MODIFY `status` ENUM('pending_approval','available','reserved','rented','hidden','rejected')
    NOT NULL DEFAULT 'pending_approval';

UPDATE `properties` SET `status` = 'reserved' WHERE `status` = 'booked';

SET FOREIGN_KEY_CHECKS = 1;
