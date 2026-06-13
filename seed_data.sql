-- ============================================================================
-- RentBridge — Seed Data for Testing
-- ============================================================================
--
-- ⚠️  Run this AFTER schema.sql is loaded (clean DB).
-- ⚠️  All test users have password: Test1234!
--
-- HOW TO USE:
--   1. Open phpMyAdmin → select dbrb_2026
--   2. SQL tab → paste this file → Go
--   3. Refresh: should see 18 users, 10 properties, 8 bookings, etc.
--
-- LOGIN CREDENTIALS (all share password: Test1234!):
--   admin@rentbridge.local            — Admin
--   jiaxi@student.utem.edu.my          — Student (active tenant)
--   meiling@student.utem.edu.my        — Student (looking)
--   alibaba@student.utem.edu.my        — Student (new)
--   ramesh@student.utem.edu.my         — Student (in pending booking)
--   siti@student.utem.edu.my           — Student (cancelled tenant)
--   weizhe@student.utem.edu.my         — Student (active)
--   farah@student.utem.edu.my          — Student (suspended/deactivated)
--   kelvin@student.utem.edu.my         — Student (signed contract)
--
--   ahmad@landlord.com                 — Landlord (verified, multi-prop)
--   wong@landlord.com                  — Landlord (pending property)
--   priya@landlord.com                 — Landlord (rented out)
--   chen@landlord.com                  — Landlord (no WhatsApp)
--   raj@landlord.com                   — Landlord (rejected property)
--
--   inspector1@utem.edu.my             — Agent (FTMK, active)
--   inspector2@utem.edu.my             — Agent (FKE, busy)
--   inspector3@utem.edu.my             — Agent (pending approval)
--   inspector4@utem.edu.my             — Agent (FKM, off duty)
-- ============================================================================

-- Disable FK checks during inserts
SET FOREIGN_KEY_CHECKS = 0;

-- Clean out everything except the bootstrap admin
DELETE FROM messages;
DELETE FROM conversations;
DELETE FROM friends;
DELETE FROM friend_requests;
DELETE FROM notifications;
DELETE FROM agent_commissions;
DELETE FROM move_in_photos;
DELETE FROM move_in_inspections;
DELETE FROM agent_verification_photos;
DELETE FROM agent_verifications;
DELETE FROM contracts;
DELETE FROM bookings;
DELETE FROM property_images;
DELETE FROM properties;
DELETE FROM agents;
DELETE FROM landlords;
DELETE FROM students;
DELETE FROM users WHERE email != 'admin@rentbridge.local';

-- Reset auto-increments
ALTER TABLE users AUTO_INCREMENT = 2;  -- admin keeps id=1
ALTER TABLE properties AUTO_INCREMENT = 1;
ALTER TABLE bookings AUTO_INCREMENT = 1;
ALTER TABLE contracts AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE conversations AUTO_INCREMENT = 1;
ALTER TABLE messages AUTO_INCREMENT = 1;
ALTER TABLE agent_verifications AUTO_INCREMENT = 1;
ALTER TABLE agent_commissions AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- USERS (all passwords =Test1234!)
-- Hash: $2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u
-- ============================================================================

-- STUDENTS (ids 2-9)
INSERT INTO users (id, email, password_hash, primary_role, status, created_at) VALUES
(2, 'jiaxi@student.utem.edu.my',  '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active',    '2026-01-15 10:00:00'),
(3, 'meiling@student.utem.edu.my','$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active',    '2026-02-03 14:22:00'),
(4, 'alibaba@student.utem.edu.my','$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active',    '2026-03-10 09:15:00'),
(5, 'ramesh@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active',    '2026-03-20 11:00:00'),
(6, 'siti@student.utem.edu.my',   '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active',    '2026-04-05 16:45:00'),
(7, 'weizhe@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active',    '2026-04-12 08:30:00'),
(8, 'farah@student.utem.edu.my',  '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'suspended', '2026-04-20 13:00:00'),
(9, 'kelvin@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active',    '2026-05-01 10:10:00');

-- LANDLORDS (ids 10-14)
INSERT INTO users (id, email, password_hash, primary_role, status, created_at) VALUES
(10, 'ahmad@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', '2026-01-20 09:00:00'),
(11, 'wong@landlord.com',  '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', '2026-02-15 11:30:00'),
(12, 'priya@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', '2026-02-28 14:15:00'),
(13, 'chen@landlord.com',  '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', '2026-03-08 16:00:00'),
(14, 'raj@landlord.com',   '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', '2026-04-12 10:45:00');

-- AGENTS (ids 15-18)
INSERT INTO users (id, email, password_hash, primary_role, status, created_at) VALUES
(15, 'inspector1@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active',  '2026-01-05 08:00:00'),
(16, 'inspector2@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active',  '2026-01-10 09:30:00'),
(17, 'inspector3@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'pending', '2026-05-15 14:00:00'),
(18, 'inspector4@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active',  '2026-02-01 10:00:00');

-- ============================================================================
-- STUDENT PROFILES
-- ============================================================================
INSERT INTO students (user_id, full_name, preferred_name, matric_no, university, phone, looking_for_housing, housing_pref_city, housing_pref_max_rent, housing_pref_move_in, housing_bio) VALUES
(2, 'Wong Jia Xi',         'Jia Xi',     'B032310495', 'UTeM', '012-3456789', 0, NULL, NULL, NULL, NULL),
(3, 'Lim Mei Ling',        'Mei Ling',   'B032310123', 'UTeM', '012-9876543', 1, 'Ayer Keroh',     500.00, '2026-09-01', 'Quiet, study-focused, non-smoker. Looking for a clean place near campus.'),
(4, 'Ali Bin Abdullah',    'Ali',        'B032310234', 'UTeM', '013-1234567', 1, 'Durian Tunggal', 600.00, '2026-08-15', 'Easy-going engineering student. Like cooking on weekends.'),
(5, 'Ramesh Kumar',        'Ramesh',     'B032310345', 'UTeM', '011-2233445', 0, NULL, NULL, NULL, NULL),
(6, 'Siti Aishah',         'Aishah',     'B032310456', 'UTeM', '019-3344556', 0, NULL, NULL, NULL, NULL),
(7, 'Tan Wei Zhe',         'Wei Zhe',    'B032310567', 'UTeM', '012-4455667', 0, NULL, NULL, NULL, NULL),
(8, 'Farah Aliyah',        'Farah',      'B032310678', 'UTeM', '014-5566778', 0, NULL, NULL, NULL, NULL),
(9, 'Kelvin Lee',          'Kelvin',     'B032310789', 'UTeM', '012-6677889', 0, NULL, NULL, NULL, NULL);

-- ============================================================================
-- LANDLORD PROFILES
-- ============================================================================
INSERT INTO landlords (user_id, full_name, preferred_name, ic_no, phone, address, verified, allow_whatsapp) VALUES
(10, 'Ahmad Bin Hassan',   'Ahmad',  '780512-04-5678', '012-1112233', 'No 23, Jalan Sutera 5, Taman Sutera, 75450 Ayer Keroh, Melaka',     1, 1),
(11, 'Wong Soo Lan',       'Wong',   '850923-08-1234', '012-2223344', 'No 15, Jalan Indah 7, Taman Indah, 75450 Ayer Keroh, Melaka',       0, 1),
(12, 'Priya A/P Subramaniam','Priya','820714-06-9012', '012-3334455', 'No 88, Lorong Permai 2, Bukit Beruang, 75450 Melaka',               1, 1),
(13, 'Chen Wei Ming',      'Chen',   '770308-10-3456', '012-4445566', 'No 7, Jalan Cheng Heng, Taman Cheng, 75250 Melaka',                 1, 0),
(14, 'Raj Singh',          'Raj',    '700615-14-7890', '012-5556677', 'No 42, Jalan Bunga Raya, Taman Bunga, 75100 Melaka',                0, 1);

-- ============================================================================
-- AGENT PROFILES
-- ============================================================================
INSERT INTO agents (user_id, full_name, preferred_name, staff_id, department, phone, availability, max_caseload, current_caseload, allow_whatsapp) VALUES
(15, 'Dr. Aminah Binti Yusof',     'Aminah',  'AGT001', 'FTMK', '012-7778899', 'available', 5, 1, 1),
(16, 'En. Kumaran A/L Selvam',     'Kumaran', 'AGT002', 'FKE',  '012-8889900', 'busy',      5, 3, 1),
(17, 'Cik Nurul Aiman',            'Nurul',   'AGT003', 'FKM',  '012-9990011', 'available', 5, 0, 0),
(18, 'Mr. Lim Chee Keong',         'Chee Keong','AGT004','FTMK', '012-0001122', 'off_duty', 3, 0, 1);

-- ============================================================================
-- PROPERTIES (showcasing every status)
-- ============================================================================
INSERT INTO properties (id, landlord_id, title, property_type, address, city, postcode, state, monthly_rent, deposit, description, facilities, furnishing, status, viewing_mode, agent_verified_at, agent_verified_by, created_at) VALUES
(1, 10, 'Cozy Single Room Near UTeM Main Gate',     'room',       'No 23, Jalan Sutera 5, Taman Sutera',           'Ayer Keroh',     '75450', 'Melaka', 450.00, 450.00, 'A bright, well-ventilated single room walking distance to UTeM main entrance. Comes with bed, study table, wardrobe.', 'WiFi, attached bathroom, parking, kitchen access', 'partial', 'available',        'either',      '2026-04-15 10:00:00', 15, '2026-04-10 09:00:00'),
(2, 10, 'Master Bedroom with Aircond',              'room',       'No 23, Jalan Sutera 5, Taman Sutera',           'Ayer Keroh',     '75450', 'Melaka', 600.00, 600.00, 'Upgraded master room with private bathroom, queen bed and air-conditioning.',                                       'WiFi, aircond, private bathroom, parking',     'full',    'rented',           'either',      '2026-05-01 11:00:00', 15, '2026-04-12 14:00:00'),
(3, 11, 'Studio Apartment in Taman Indah',           'studio',     'No 15, Jalan Indah 7, Taman Indah',             'Ayer Keroh',     '75450', 'Melaka', 850.00, 850.00, 'Compact studio for one person, fully furnished. Brand new building.',                                              'WiFi, aircond, kitchen, gym access',           'full',    'pending_approval', 'agent_led',   NULL,                  NULL, '2026-06-08 10:30:00'),
(4, 12, '3-Bedroom Whole Unit, Bukit Beruang',       'whole_unit', 'No 88, Lorong Permai 2',                        'Bukit Beruang',  '75450', 'Melaka',2400.00,2400.00, 'Spacious 3-bedroom terrace house for groups of 3-4 students. Recently renovated.',                                  'WiFi, aircond, washing machine, parking 2 cars','full',    'booked',           'landlord_led','2026-05-20 14:00:00', 16, '2026-04-25 09:00:00'),
(5, 12, 'Quiet Bedroom for Studious Student',        'room',       'No 88, Lorong Permai 2',                        'Bukit Beruang',  '75450', 'Melaka', 500.00, 500.00, 'Peaceful neighborhood, ideal for serious students. Strict no-party policy.',                                       'WiFi, study desk, shared kitchen',              'partial', 'available',        'either',      '2026-05-15 09:00:00', 16, '2026-05-01 11:00:00'),
(6, 13, 'Cheng Heng Family-Style Room',              'room',       'No 7, Jalan Cheng Heng',                        'Cheng',          '75250', 'Melaka', 380.00, 380.00, 'Old-school family home with affordable rent. Landlord stays nearby. Great for budget-conscious students.',         'WiFi, parking, garden',                         'partial', 'available',        'landlord_led', NULL,                 NULL, '2026-05-10 13:00:00'),
(7, 13, 'Hidden Listing — Maintenance',              'room',       'No 7, Jalan Cheng Heng',                        'Cheng',          '75250', 'Melaka', 350.00, 350.00, 'Temporarily off the market while plumbing is being repaired.',                                                      'WiFi, parking',                                 'none',    'hidden',           'either',      NULL,                  NULL, '2026-04-20 10:00:00'),
(8, 14, 'Bunga Raya Suspicious Listing',             'room',       'No 42, Jalan Bunga Raya',                       'Melaka City',    '75100', 'Melaka', 250.00,  0.00, 'Very cheap room, no deposit needed. Contact me direct.',                                                            'Nothing',                                       'none',    'rejected',         'either',      NULL,                  NULL, '2026-05-25 18:00:00'),
(9, 11, 'Newly Listed Studio (Awaiting Approval)',  'studio',     'No 15, Jalan Indah 7, Block B',                 'Ayer Keroh',     '75450', 'Melaka', 800.00, 800.00, 'Brand new studio unit, just finished renovation. Waiting for admin approval.',                                      'WiFi, aircond, kitchen',                        'full',    'pending_approval', 'either',      NULL,                  NULL, '2026-06-09 15:00:00'),
(10,12, 'Beruang Family Home Whole Unit',            'whole_unit', 'No 90, Lorong Permai 2',                        'Bukit Beruang',  '75450', 'Melaka',1800.00,1800.00, 'Whole single-storey terrace, ideal for 3 students.',                                                                'WiFi, aircond in living room, parking',         'partial', 'available',        'either',      '2026-05-25 10:00:00', 18, '2026-05-12 12:00:00');

-- ============================================================================
-- PROPERTY IMAGES (placeholder paths — you'll need to add actual files)
-- ============================================================================
INSERT INTO property_images (property_id, image_path, is_primary) VALUES
(1, 'uploads/properties/seed_room_01.jpg', 1),
(1, 'uploads/properties/seed_room_02.jpg', 0),
(2, 'uploads/properties/seed_room_03.jpg', 1),
(3, 'uploads/properties/seed_studio_01.jpg', 1),
(4, 'uploads/properties/seed_house_01.jpg', 1),
(4, 'uploads/properties/seed_house_02.jpg', 0),
(5, 'uploads/properties/seed_room_04.jpg', 1),
(6, 'uploads/properties/seed_room_05.jpg', 1),
(9, 'uploads/properties/seed_studio_02.jpg', 1),
(10,'uploads/properties/seed_house_03.jpg', 1);

-- ============================================================================
-- BOOKINGS (every status represented)
-- ============================================================================
INSERT INTO bookings (id, student_id, property_id, landlord_id, agent_id, start_date, end_date, duration_type, monthly_rent, deposit, status, student_note, created_at) VALUES
-- 1: Active tenancy (Jia Xi renting Ahmad's master room)
(1, 2, 2, 10, 15, '2026-05-01', '2027-04-30', '1_year',      600.00, 600.00, 'active',
    'Looking forward to moving in soon, thanks!',
    '2026-04-12 10:00:00'),

-- 2: Active tenancy with co-tenants on a whole unit (Kelvin renting Priya's house)
(2, 9, 4, 12, 16, '2026-06-01', '2027-05-31', '1_year',     2400.00,2400.00, 'active',
    'I will rent with two other friends.',
    '2026-05-15 11:00:00'),

-- 3: Pending landlord — Mei Ling wants Cozy Room
(3, 3, 1, 10, NULL, '2026-08-01', '2027-07-31', '1_year',    450.00, 450.00, 'pending_landlord',
    'I am a quiet student, would love to take this room.',
    '2026-06-09 09:00:00'),

-- 4: Pending agent — landlord approved, awaiting agent assignment
(4, 4, 5, 12, NULL, '2026-09-01', '2027-08-31', '1_year',    500.00, 500.00, 'pending_agent',
    'Hi, can I book this room for the September semester?',
    '2026-06-09 14:00:00'),

-- 5: Agent verifying — inspection in progress
(5, 5, 5, 12, 15, '2026-09-15', '2027-08-31', '1_year',    500.00, 500.00, 'agent_verifying',
    'Booking for September.',
    '2026-06-10 09:00:00'),

-- 6: Verification failed (admin will see)
(6, 6, 6, 13, 18, '2026-08-15', '2026-12-15', 'custom',      380.00, 380.00, 'verification_failed',
    'Need it for one semester.',
    '2026-05-28 16:00:00'),

-- 7: Cancelled by student (post-disclosure decision to walk away)
(7, 6, 10, 12, 16, '2026-09-01', '2027-08-31', '1_year',    1800.00,1800.00, 'cancelled_by_student',
    'Heard from friend the area was nice.',
    '2026-05-20 10:00:00'),

-- 8: Completed tenancy (Ramesh did 4 months at the cheap Cheng room)
(8, 5, 6, 13, 18, '2026-01-15', '2026-05-14', 'custom',     380.00, 380.00, 'completed',
    'One semester only.',
    '2026-01-10 09:00:00');

-- Update student cancellations
UPDATE bookings SET cancellation_reason = 'Decided to rent with another group instead.', cancelled_by = 6 WHERE id = 7;
UPDATE bookings SET cancellation_reason = 'Property did not match listing photos. Walls had mold not shown in pictures.' WHERE id = 6;

-- ============================================================================
-- AGENT VERIFICATIONS
-- ============================================================================
-- For booking 1 (active) — verification passed cleanly
INSERT INTO agent_verifications (id, booking_id, agent_id, started_at, submitted_at, deadline_at, property_matches_listing, property_address_correct, facilities_match, landlord_id_matches, ownership_doc_sighted, inspection_notes, issue_severity, outcome) VALUES
(1, 1, 15, '2026-04-13 09:00:00', '2026-04-15 10:00:00', '2026-04-18 09:00:00',
    1, 1, 1, 1, 1,
    'Property is in excellent condition. Landlord cooperative. All facilities match listing.',
    'none', 'passed');

-- For booking 2 (active, whole unit) — verification passed
INSERT INTO agent_verifications (id, booking_id, agent_id, started_at, submitted_at, deadline_at, property_matches_listing, property_address_correct, facilities_match, landlord_id_matches, ownership_doc_sighted, inspection_notes, issue_severity, outcome) VALUES
(2, 2, 16, '2026-05-16 10:00:00', '2026-05-20 14:00:00', '2026-05-21 10:00:00',
    1, 1, 1, 1, 1,
    'Beautiful renovated unit. Owner is the registered landlord. All checks pass.',
    'none', 'passed');

-- For booking 5 (currently verifying)
INSERT INTO agent_verifications (id, booking_id, agent_id, started_at, deadline_at, outcome) VALUES
(3, 5, 15, '2026-06-10 14:00:00', '2026-06-15 14:00:00', 'in_progress');

-- For booking 6 (failed)
INSERT INTO agent_verifications (id, booking_id, agent_id, started_at, submitted_at, deadline_at, property_matches_listing, property_address_correct, facilities_match, landlord_id_matches, ownership_doc_sighted, inspection_notes, issues_found, issue_severity, outcome) VALUES
(4, 6, 18, '2026-05-29 10:00:00', '2026-05-31 16:00:00', '2026-06-03 10:00:00',
    0, 1, 0, 1, 1,
    'Property is structurally OK and landlord identity verified, BUT condition is much worse than photos suggest.',
    'Major mold issue on north-facing wall in bedroom. Bathroom plumbing leaks. WiFi router not present. Listing photos appear to be 2+ years old. Recommend property be re-listed only after these are fixed.',
    'major', 'failed');

-- For booking 8 (completed — old inspection)
INSERT INTO agent_verifications (id, booking_id, agent_id, started_at, submitted_at, deadline_at, property_matches_listing, property_address_correct, facilities_match, landlord_id_matches, ownership_doc_sighted, inspection_notes, issue_severity, outcome) VALUES
(5, 8, 18, '2026-01-11 10:00:00', '2026-01-13 15:00:00', '2026-01-16 10:00:00',
    1, 1, 1, 1, 1,
    'Older property but well-maintained. Landlord is friendly.',
    'none', 'passed');

-- ============================================================================
-- CONTRACTS
-- ============================================================================
INSERT INTO contracts (id, contract_code, booking_id, student_id, landlord_id, agent_id, property_id, start_date, end_date, monthly_rent, deposit, terms, status, activated_at, student_signed_at, landlord_signed_at, agent_signed_at, created_at) VALUES
-- Contract 1: ACTIVE (booking 1, all signed)
(1, 'RB-2026-00001', 1, 2, 10, 15, 2,
    '2026-05-01', '2027-04-30', 600.00, 600.00,
    'Standard one-year tenancy agreement. The Tenant agrees to pay monthly rent on or before the 1st of each month. The Landlord agrees to maintain the property in habitable condition. Termination requires 30 days written notice.',
    'active', '2026-04-22 14:00:00', '2026-04-20 10:30:00', '2026-04-21 16:00:00', '2026-04-22 13:00:00', '2026-04-18 09:00:00'),

-- Contract 2: ACTIVE (booking 2, all signed — whole unit)
(2, 'RB-2026-00002', 2, 9, 12, 16, 4,
    '2026-06-01', '2027-05-31', 2400.00, 2400.00,
    'Standard one-year tenancy agreement for the whole unit. The Tenant and co-tenants agree to share monthly rent equally and pay before the 1st of each month.',
    'active', '2026-05-28 17:00:00', '2026-05-25 11:00:00', '2026-05-27 14:00:00', '2026-05-28 16:00:00', '2026-05-22 10:00:00'),

-- Contract 3: COMPLETED (booking 8 — finished)
(3, 'RB-2026-00003', 8, 5, 13, 18, 6,
    '2026-01-15', '2026-05-14', 380.00, 380.00,
    'Standard 4-month semester tenancy agreement.',
    'completed', '2026-01-14 11:00:00', '2026-01-13 16:00:00', '2026-01-14 09:00:00', '2026-01-14 10:30:00', '2026-01-13 15:30:00');

-- ============================================================================
-- AGENT COMMISSIONS
-- ============================================================================
INSERT INTO agent_commissions (contract_id, agent_id, base_rent, commission_pct, commission_amt, sst_pct, sst_amt, total_payable, status, earned_at, released_at, paid_at) VALUES
(1, 15, 600.00,  100.00, 600.00,  6.00, 36.00,  636.00, 'paid',     '2026-04-22 14:00:00', '2026-04-25 10:00:00', '2026-04-30 10:00:00'),
(2, 16, 2400.00, 100.00, 2400.00, 6.00, 144.00, 2544.00, 'earned',  '2026-05-28 17:00:00', NULL, NULL),
(3, 18, 380.00,  100.00, 380.00,  6.00, 22.80,  402.80, 'paid',     '2026-01-14 11:00:00', '2026-01-20 10:00:00', '2026-02-01 09:00:00');

-- ============================================================================
-- NOTIFICATIONS (sample for various users)
-- ============================================================================
INSERT INTO notifications (user_id, type, title, message, link_url, is_read, created_at) VALUES
-- For Mei Ling: pending booking
(3, 'booking_pending', 'Your booking is awaiting landlord response', 'Booking #3 for "Cozy Single Room Near UTeM Main Gate" is awaiting landlord response.', '/rentbridge/student/booking.php?id=3', 0, '2026-06-09 09:01:00'),
-- For Ahmad: booking request to review
(10, 'booking_request', 'New tenancy application', 'Lim Mei Ling submitted booking #3 for "Cozy Single Room Near UTeM Main Gate".', '/rentbridge/landlord/booking.php?id=3', 0, '2026-06-09 09:01:00'),
-- For Aminah (agent): case to inspect
(15, 'agent_assigned', 'New inspection case assigned', 'Booking #5 needs property inspection within 5 days.', '/rentbridge/agent/inspection.php?booking_id=5', 0, '2026-06-10 14:01:00'),
-- For Jia Xi: tenancy active
(2, 'contract_active', 'Tenancy active!', 'Your contract RB-2026-00001 is now active.', '/rentbridge/student/booking.php?id=1', 1, '2026-04-22 14:01:00'),
-- For Ahmad: tenancy active
(10, 'contract_active', 'Tenancy active!', 'Contract RB-2026-00001 has been signed by all parties.', '/rentbridge/landlord/booking.php?id=1', 1, '2026-04-22 14:01:00'),
-- For admin: stuck booking
(1, 'admin_alert', 'Booking needs manual review', 'Booking #4 has been pending agent assignment for more than 24 hours.', '/rentbridge/admin/booking.php?id=4', 0, '2026-06-09 15:00:00'),
-- For Aishah: cancelled tenancy
(6, 'booking_cancelled', 'Tenancy cancelled', 'Tenancy #6 cancelled due to failed inspection.', '/rentbridge/student/bookings.php', 1, '2026-05-31 17:00:00'),
-- For Wong (landlord): property pending
(11, 'property_pending', 'Property awaiting review', 'Your property "Studio Apartment in Taman Indah" is awaiting admin approval.', '/rentbridge/landlord/properties.php', 0, '2026-06-08 10:31:00'),
-- For admin: property to review
(1, 'admin_alert', 'New property awaiting review', 'Wong Soo Lan submitted "Studio Apartment in Taman Indah" for approval.', '/rentbridge/admin/property.php?id=3', 0, '2026-06-08 10:31:00'),
-- For Kelvin: tenancy active
(9, 'contract_active', 'Tenancy active!', 'Your contract RB-2026-00002 is now active.', '/rentbridge/student/booking.php?id=2', 1, '2026-05-28 17:01:00');

-- ============================================================================
-- CONVERSATIONS + MESSAGES (chat system test data)
-- ============================================================================
-- Convo 1: Mei Ling ↔ Ahmad (about property 1)
INSERT INTO conversations (id, user_a, user_b, property_id, context_type, last_message_at, last_message_preview, last_sender_id, created_at) VALUES
(1, 3, 10, 1, 'property_inquiry', '2026-06-09 09:30:00', 'Sure, let me arrange a viewing this Saturday.', 10, '2026-06-08 18:00:00'),
(2, 4, 12, 5, 'property_inquiry', '2026-06-09 14:30:00', 'Yes the room is still available!', 12, '2026-06-09 14:00:00'),
(3, 2, 10, 2, 'booking',          '2026-04-21 17:00:00', 'Welcome aboard, looking forward to seeing you.', 10, '2026-04-15 11:00:00'),
(4, 9, 12, 4, 'booking',          '2026-05-27 15:00:00', 'Contract sent for signing.', 12, '2026-05-20 12:00:00'),
(5, 6, 13, 6, 'property_inquiry', '2026-05-30 10:00:00', 'I am sorry about the mold issue, I will fix it.', 13, '2026-05-28 17:00:00');

INSERT INTO messages (conversation_id, sender_id, body, sent_at, read_at) VALUES
-- Conversation 1
(1, 3,  'Hi! I am interested in the Cozy Single Room. Is it still available for September?',  '2026-06-08 18:00:00', '2026-06-08 19:00:00'),
(1, 10, 'Hello Mei Ling, yes still available. Would you like to view it?',                    '2026-06-09 08:30:00', '2026-06-09 09:00:00'),
(1, 3,  'Yes please. When is convenient for you?',                                            '2026-06-09 09:00:00', '2026-06-09 09:25:00'),
(1, 10, 'Sure, let me arrange a viewing this Saturday.',                                      '2026-06-09 09:30:00', NULL),

-- Conversation 2
(2, 4,  'Hi, is the Quiet Bedroom still available?',                                          '2026-06-09 14:00:00', '2026-06-09 14:15:00'),
(2, 12, 'Yes the room is still available!',                                                   '2026-06-09 14:30:00', NULL),

-- Conversation 3 (existing tenant)
(3, 2,  'Hi Ahmad, thank you for accepting my booking.',                                      '2026-04-15 11:00:00', '2026-04-15 12:00:00'),
(3, 10, 'My pleasure Jia Xi! See you on May 1st.',                                            '2026-04-15 14:00:00', '2026-04-15 14:30:00'),
(3, 2,  'Could I move in a day earlier? My old place ends April 30.',                         '2026-04-21 16:00:00', '2026-04-21 16:30:00'),
(3, 10, 'Welcome aboard, looking forward to seeing you.',                                     '2026-04-21 17:00:00', '2026-04-22 09:00:00'),

-- Conversation 4
(4, 9,  'Hi Priya, my two friends will join me for the whole unit.',                          '2026-05-20 12:00:00', '2026-05-20 13:00:00'),
(4, 12, 'No problem. Please list their names in the booking note.',                           '2026-05-20 14:00:00', '2026-05-20 15:00:00'),
(4, 9,  'Done! Tan Wei Zhe and Lim Mei Ling.',                                                '2026-05-22 09:00:00', '2026-05-22 10:00:00'),
(4, 12, 'Contract sent for signing.',                                                         '2026-05-27 15:00:00', '2026-05-27 16:00:00'),

-- Conversation 5 (failed inspection)
(5, 6,  'Hi, the agent said the property has mold and the booking was cancelled?',           '2026-05-28 17:30:00', '2026-05-29 09:00:00'),
(5, 13, 'I am sorry about the mold issue, I will fix it.',                                    '2026-05-30 10:00:00', NULL);

-- ============================================================================
-- VERIFY
-- ============================================================================
-- Should show:
--   Users:      18  (1 admin + 8 students + 5 landlords + 4 agents)
--   Properties: 10
--   Bookings:   8
--   Contracts:  3
--   Conversations: 5
--   Messages:   13
--   Notifications: 10
-- ============================================================================

SELECT 'Users'         AS table_name, COUNT(*) AS row_count FROM users
UNION ALL SELECT 'Students',      COUNT(*) FROM students
UNION ALL SELECT 'Landlords',     COUNT(*) FROM landlords
UNION ALL SELECT 'Agents',        COUNT(*) FROM agents
UNION ALL SELECT 'Properties',    COUNT(*) FROM properties
UNION ALL SELECT 'Bookings',      COUNT(*) FROM bookings
UNION ALL SELECT 'Contracts',     COUNT(*) FROM contracts
UNION ALL SELECT 'Verifications', COUNT(*) FROM agent_verifications
UNION ALL SELECT 'Commissions',   COUNT(*) FROM agent_commissions
UNION ALL SELECT 'Conversations', COUNT(*) FROM conversations
UNION ALL SELECT 'Messages',      COUNT(*) FROM messages
UNION ALL SELECT 'Notifications', COUNT(*) FROM notifications;