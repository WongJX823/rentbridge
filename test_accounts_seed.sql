-- ============================================================
-- test_accounts_seed.sql
-- Seeds the 9 test accounts defined in RentBridge_TestCase_Prompt.md
-- Password for all accounts: Test@1234
-- Hash: $2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW
-- Run AFTER dbrb_2026.sql. Safe to re-run (INSERT IGNORE).
-- ============================================================
SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

-- ============================================================
-- USERS TABLE (IDs 9901-9909)
-- ============================================================
INSERT IGNORE INTO `users` (id, email, password_hash, primary_role, status, created_at, updated_at) VALUES
(9901, 's1@test.com',    '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'student',  'active', NOW(), NOW()),
(9902, 's2@test.com',    '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'student',  'active', NOW(), NOW()),
(9903, 's3@test.com',    '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'student',  'active', NOW(), NOW()),
(9904, 's4@test.com',    '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'student',  'active', NOW(), NOW()),
(9905, 's5@test.com',    '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'student',  'active', NOW(), NOW()),
(9906, 's6@test.com',    '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'student',  'active', NOW(), NOW()),
(9907, 'll@test.com',    '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'landlord', 'active', NOW(), NOW()),
(9908, 'agt@test.com',   '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'agent',    'active', NOW(), NOW()),
(9909, 'admin@test.com', '$2y$10$In3Bv0nV.gAyomhuruFEzuJHqGwypazgGZ0oCxCWCbYU8VXwx5TjW', 'admin',    'active', NOW(), NOW());

-- ============================================================
-- STUDENTS PROFILE TABLE
-- ============================================================
INSERT IGNORE INTO `students` (user_id, full_name, preferred_name, matric_no, university, phone, allow_whatsapp, looking_for_housing) VALUES
(9901, 'Ahmad Faris',    'Faris',   'B032300001', 'UTeM', '011-23456781', 0, 1),
(9902, 'Lim Wei Xian',   'Wei Xian','B032300002', 'UTeM', '012-34567892', 0, 1),
(9903, 'Priya Nair',     'Priya',   'B032300003', 'UTeM', '013-98765433', 0, 1),
(9904, 'Nurul Ain',      'Ain',     'B032300004', 'UTeM', '014-56789014', 0, 1),
(9905, 'Tan Jia Hui',    'Jia Hui', 'B032300005', 'UTeM', '015-67890125', 0, 1),
(9906, 'Hafiz Zulkifli', 'Hafiz',   'B032300006', 'UTeM', '016-78901236', 0, 1);

-- ============================================================
-- LANDLORDS PROFILE TABLE
-- ============================================================
INSERT IGNORE INTO `landlords` (user_id, full_name, preferred_name, ic_no, phone, allow_whatsapp, address, verified) VALUES
(9907, 'Encik Roslan', 'Roslan', '700101-14-5678', '019-12345670', 0, 'No. 10, Jalan Bukit Beruang, Ayer Keroh, Melaka', 1);

-- ============================================================
-- AGENTS PROFILE TABLE
-- ============================================================
INSERT IGNORE INTO `agents` (user_id, full_name, preferred_name, staff_id, department, phone, allow_whatsapp, availability, max_caseload, current_caseload) VALUES
(9908, 'Agent Siti', 'Siti', 'AGT-TEST-001', 'HEP UTeM', '013-99887760', 0, 'available', 10, 0);

SET FOREIGN_KEY_CHECKS=1;

-- Verify
SELECT id, email, primary_role, status FROM users WHERE id >= 9901 ORDER BY id;
