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

--============================================================================
-- 13.6.26
--============================================================================
-- ============================================================================
-- RentBridge — EXTRA Seed Data for Chart Visualization
-- ============================================================================
--
-- Run AFTER seed_data.sql (the base 18 users + 10 properties)
-- This adds: more users, more properties, more tenancies SPREAD ACROSS MONTHS
-- so the trend charts have meaningful data.
--
-- TOTAL after this: ~40 users, ~30 properties, ~25 tenancies, ~15 contracts
-- Spread over 6 months (Jan 2026 → Jun 2026) for time-series charts
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- MORE STUDENTS (10 extra — ids 19-28)
-- All passwords = Test1234!
-- ============================================================================
INSERT INTO users (id, email, password_hash, primary_role, status, created_at) VALUES
(19, 'azlan@student.utem.edu.my',   '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-01-08 09:00:00'),
(20, 'devi@student.utem.edu.my',    '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-01-22 10:30:00'),
(21, 'farid@student.utem.edu.my',   '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-02-05 11:00:00'),
(22, 'kavitha@student.utem.edu.my', '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-02-18 14:00:00'),
(23, 'syafiq@student.utem.edu.my',  '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-03-02 10:00:00'),
(24, 'jasmine@student.utem.edu.my', '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-03-15 09:30:00'),
(25, 'hafiz@student.utem.edu.my',   '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-04-08 16:00:00'),
(26, 'amelia@student.utem.edu.my',  '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-04-25 13:00:00'),
(27, 'zafri@student.utem.edu.my',   '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-05-12 11:00:00'),
(28, 'nadia@student.utem.edu.my',   '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'student', 'active', '2026-06-01 10:00:00');

INSERT INTO students (user_id, full_name, preferred_name, matric_no, university, phone, looking_for_housing) VALUES
(19, 'Mohd Azlan Bin Ismail',     'Azlan',    'B032310890', 'UTeM', '012-7890123', 0),
(20, 'Devi A/P Murugan',          'Devi',     'B032310901', 'UTeM', '012-8901234', 1),
(21, 'Mohd Farid Bin Hashim',     'Farid',    'B032310912', 'UTeM', '011-9012345', 0),
(22, 'Kavitha A/P Selvaraj',      'Kavitha',  'B032310923', 'UTeM', '019-0123456', 1),
(23, 'Mohd Syafiq Bin Adnan',     'Syafiq',   'B032310934', 'UTeM', '012-1234560', 0),
(24, 'Jasmine Tan',               'Jasmine',  'B032310945', 'UTeM', '013-2345601', 0),
(25, 'Mohd Hafiz Bin Yusoff',     'Hafiz',    'B032310956', 'UTeM', '014-3456012', 0),
(26, 'Amelia Wong',               'Amelia',   'B032310967', 'UTeM', '012-4560123', 1),
(27, 'Mohd Zafri Bin Karim',      'Zafri',    'B032310978', 'UTeM', '015-5601234', 0),
(28, 'Nadia Binti Razak',         'Nadia',    'B032310989', 'UTeM', '016-6012345', 1);

-- ============================================================================
-- MORE LANDLORDS (5 extra — ids 29-33)
-- ============================================================================
INSERT INTO users (id, email, password_hash, primary_role, status, created_at) VALUES
(29, 'fauziah@landlord.com', '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'landlord', 'active', '2026-01-10 11:00:00'),
(30, 'tan@landlord.com',     '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'landlord', 'active', '2026-02-08 14:00:00'),
(31, 'ismail@landlord.com',  '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'landlord', 'active', '2026-03-12 10:00:00'),
(32, 'kumar@landlord.com',   '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'landlord', 'active', '2026-04-20 15:00:00'),
(33, 'lim@landlord.com',     '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'landlord', 'active', '2026-05-25 11:00:00');

INSERT INTO landlords (user_id, full_name, preferred_name, ic_no, phone, allow_whatsapp) VALUES
(29, 'Fauziah Binti Saad',    'Fauziah', '760412-04-1122', '012-7771234', 1),
(30, 'Tan Boon Heng',         'Tan',     '690819-08-3344', '012-7772345', 0),
(31, 'Ismail Bin Yaakub',     'Ismail',  '720625-06-5566', '012-7773456', 1),
(32, 'Kumar A/L Raman',       'Kumar',   '801107-10-7788', '012-7774567', 1),
(33, 'Lim Soo Mei',           'Lim',     '850314-08-9900', '012-7775678', 0);

-- ============================================================================
-- MORE AGENTS (2 extra — ids 34-35)
-- ============================================================================
INSERT INTO users (id, email, password_hash, primary_role, status, created_at) VALUES
(34, 'inspector5@utem.edu.my', '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'agent', 'active', '2026-02-15 09:00:00'),
(35, 'inspector6@utem.edu.my', '$2y$10$ZW6h.NJ5J9D0LJZG3LHNoOzCJSDjFJgWnZK5jZ2HxqB7w/N1bL7Bm', 'agent', 'active', '2026-03-20 10:00:00');

INSERT INTO agents (user_id, full_name, preferred_name, staff_id, department, phone, availability, max_caseload, current_caseload, allow_whatsapp) VALUES
(34, 'Dr. Hairul Bin Anuar',    'Hairul', 'AGT005', 'FTMK', '012-9991111', 'available', 5, 2, 1),
(35, 'Pn. Salmah Binti Hasan',  'Salmah', 'AGT006', 'FKE',  '012-9992222', 'available', 5, 1, 1);

-- ============================================================================
-- MORE PROPERTIES (20 extra — ids 11-30)
-- Spread across cities + price ranges for meaningful chart data
-- ============================================================================
INSERT INTO properties (id, landlord_id, title, property_type, address, city, postcode, state, monthly_rent, deposit, description, facilities, furnishing, status, viewing_mode, agent_verified_at, agent_verified_by, created_at) VALUES
-- AYER KEROH (cluster of properties for "highest demand area" story)
(11, 10, 'Sutera Single Room A1', 'room', 'No 23A, Jalan Sutera 5', 'Ayer Keroh', '75450', 'Melaka', 420.00, 420.00, 'Affordable single room near campus.', 'WiFi, fan', 'partial', 'available', 'either', '2026-02-15 10:00:00', 15, '2026-02-10 09:00:00'),
(12, 10, 'Sutera Single Room A2', 'room', 'No 23B, Jalan Sutera 5', 'Ayer Keroh', '75450', 'Melaka', 460.00, 460.00, 'Slightly larger room with aircond.', 'WiFi, aircond', 'partial', 'rented', 'either', '2026-02-20 10:00:00', 15, '2026-02-12 09:00:00'),
(13, 29, 'Indah Studio Premium', 'studio', 'No 8, Jalan Indah 10', 'Ayer Keroh', '75450', 'Melaka', 900.00, 900.00, 'High-end studio with full kitchen.', 'WiFi, aircond, kitchen, gym', 'full', 'available', 'either', '2026-03-05 14:00:00', 16, '2026-02-28 11:00:00'),
(14, 29, 'Indah Studio Budget', 'studio', 'No 9, Jalan Indah 10', 'Ayer Keroh', '75450', 'Melaka', 700.00, 700.00, 'Budget studio option in same building.', 'WiFi, fan, kitchen', 'partial', 'rented', 'either', '2026-03-08 14:00:00', 16, '2026-03-01 11:00:00'),
(15, 31, 'Keroh Heights Unit 3A', 'whole_unit', 'Block A-3-2, Keroh Heights', 'Ayer Keroh', '75450', 'Melaka', 2200.00, 2200.00, '3-bedroom apartment for groups.', 'WiFi, aircond, parking', 'partial', 'available', 'either', '2026-04-10 09:00:00', 18, '2026-04-02 10:00:00'),

-- BUKIT BERUANG (mid-tier area)
(16, 12, 'Beruang Premium Room', 'room', 'No 92, Lorong Permai 2', 'Bukit Beruang', '75450', 'Melaka', 550.00, 550.00, 'Larger master room with attached bath.', 'WiFi, aircond, private bath', 'full', 'rented', 'either', '2026-02-18 10:00:00', 16, '2026-02-10 14:00:00'),
(17, 12, 'Beruang Garden View Room', 'room', 'No 94, Lorong Permai 2', 'Bukit Beruang', '75450', 'Melaka', 480.00, 480.00, 'Room facing garden.', 'WiFi, fan, garden access', 'partial', 'available', 'either', '2026-03-22 11:00:00', 18, '2026-03-15 10:00:00'),
(18, 30, 'Bukit Beruang 2-Bed', 'whole_unit', 'No 56, Jalan Permai 4', 'Bukit Beruang', '75450', 'Melaka', 1600.00, 1600.00, '2-bedroom unit for 2-3 students.', 'WiFi, aircond, parking', 'partial', 'available', 'either', '2026-04-15 14:00:00', 15, '2026-04-08 10:00:00'),

-- DURIAN TUNGGAL (cheaper area)
(19, 13, 'Tunggal Family House Room', 'room', 'No 12, Jalan Durian 3', 'Durian Tunggal', '76100', 'Melaka', 350.00, 350.00, 'Affordable room with friendly landlord.', 'WiFi, parking, garden', 'partial', 'available', 'landlord_led', '2026-03-25 09:00:00', 16, '2026-03-18 11:00:00'),
(20, 31, 'Tunggal Budget Room', 'room', 'No 45, Jalan Durian 7', 'Durian Tunggal', '76100', 'Melaka', 320.00, 320.00, 'Cheapest option near transport hub.', 'WiFi, fan', 'none', 'rented', 'landlord_led', '2026-04-02 14:00:00', 18, '2026-03-28 09:00:00'),

-- MELAKA CITY (premium area)
(21, 32, 'Melaka City Loft Studio', 'studio', 'Unit 12-3, Melaka City Tower', 'Melaka City', '75100', 'Melaka', 1100.00, 1100.00, 'Modern loft studio in city center.', 'WiFi, aircond, gym, pool', 'full', 'rented', 'either', '2026-04-25 15:00:00', 15, '2026-04-18 13:00:00'),
(22, 32, 'Melaka City Standard Studio', 'studio', 'Unit 8-5, Melaka City Tower', 'Melaka City', '75100', 'Melaka', 950.00, 950.00, 'Same building, smaller unit.', 'WiFi, aircond, gym', 'full', 'available', 'either', '2026-05-10 11:00:00', 16, '2026-05-02 09:00:00'),

-- CHENG (suburban)
(23, 33, 'Cheng Family Home Room A', 'room', 'No 18, Taman Cheng Indah', 'Cheng', '75250', 'Melaka', 380.00, 380.00, 'Quiet suburban room.', 'WiFi, parking', 'partial', 'available', 'either', '2026-05-15 10:00:00', 18, '2026-05-08 11:00:00'),
(24, 33, 'Cheng Family Home Room B', 'room', 'No 18A, Taman Cheng Indah', 'Cheng', '75250', 'Melaka', 360.00, 360.00, 'Sister room to Room A.', 'WiFi, parking', 'partial', 'rented', 'either', '2026-05-18 10:00:00', 18, '2026-05-08 11:00:00'),

-- More for variety / status diversity
(25, 11, 'Newly Listed Studio', 'studio', 'Block C-2-1, Indah Heights', 'Ayer Keroh', '75450', 'Melaka', 850.00, 850.00, 'Just listed, looking for tenants.', 'WiFi, aircond', 'full', 'pending_approval', 'either', NULL, NULL, '2026-06-05 14:00:00'),
(26, 30, 'Yet Another Pending', 'room', 'No 78, Jalan Mawar', 'Bukit Beruang', '75450', 'Melaka', 500.00, 500.00, 'Recent listing awaiting approval.', 'WiFi', 'partial', 'pending_approval', 'either', NULL, NULL, '2026-06-08 10:00:00'),
(27, 14, 'Suspicious Cheap Room', 'room', 'No 99, Jalan ABC', 'Melaka City', '75100', 'Melaka', 180.00, 0.00, 'Very cheap, no deposit.', 'Nothing', 'none', 'rejected', 'either', NULL, NULL, '2026-04-30 17:00:00'),
(28, 12, 'Premium Beruang Studio', 'studio', 'Block B-3-1, Permai Heights', 'Bukit Beruang', '75450', 'Melaka', 1050.00, 1050.00, 'Premium studio with full furnishing.', 'WiFi, aircond, kitchen, gym', 'full', 'rented', 'either', '2026-05-25 14:00:00', 16, '2026-05-18 09:00:00'),
(29, 13, 'Quiet Studio Cheng', 'studio', 'Unit 5, Taman Cheng Permai', 'Cheng', '75250', 'Melaka', 750.00, 750.00, 'Peaceful studio in residential area.', 'WiFi, aircond', 'partial', 'available', 'either', '2026-05-28 10:00:00', 15, '2026-05-22 11:00:00'),
(30, 29, 'Hidden Maintenance', 'room', 'No 9, Jalan Indah 10', 'Ayer Keroh', '75450', 'Melaka', 430.00, 430.00, 'Temporarily off market.', 'WiFi, fan', 'partial', 'hidden', 'either', NULL, NULL, '2026-03-10 11:00:00');

-- ============================================================================
-- MORE BOOKINGS (15 extra — ids 9-23) — spread across months for trend charts
-- ============================================================================
INSERT INTO bookings (id, student_id, property_id, landlord_id, agent_id, start_date, end_date, duration_type, monthly_rent, deposit, status, created_at) VALUES
-- January
(9,  19, 11, 10, 15, '2026-02-01', '2027-01-31', '1_year',    420.00, 420.00, 'completed',           '2026-01-15 10:00:00'),
(10, 20, 16, 12, 16, '2026-02-15', '2027-02-14', '1_year',    550.00, 550.00, 'active',              '2026-01-20 11:00:00'),

-- February
(11, 21, 12, 10, 15, '2026-03-01', '2027-02-28', '1_year',    460.00, 460.00, 'active',              '2026-02-15 09:00:00'),
(12, 22, 14, 29, 16, '2026-03-15', '2027-03-14', '1_year',    700.00, 700.00, 'active',              '2026-02-28 14:00:00'),

-- March
(13, 23, 20, 31, 18, '2026-04-01', '2026-07-31', 'custom',    320.00, 320.00, 'active',              '2026-03-20 10:00:00'),
(14, 24, 21, 32, 15, '2026-04-15', '2027-04-14', '1_year',   1100.00,1100.00, 'active',              '2026-03-25 16:00:00'),

-- April
(15, 25, 24, 33, 18, '2026-05-01', '2026-08-31', 'custom',    360.00, 360.00, 'active',              '2026-04-18 09:00:00'),
(16, 26, 28, 12, 16, '2026-05-15', '2027-05-14', '1_year',   1050.00,1050.00, 'active',              '2026-04-25 14:00:00'),

-- May
(17, 27, 17, 12, 16, '2026-06-01', '2026-10-31', 'custom',    480.00, 480.00, 'contract_pending',    '2026-05-20 11:00:00'),
(18, 28, 22, 32, 34, '2026-06-15', '2027-06-14', '1_year',    950.00, 950.00, 'agent_verifying',     '2026-05-30 10:00:00'),

-- June (recent, various statuses)
(19, 19, 18, 30, NULL, '2026-07-01', '2027-06-30', '1_year', 1600.00,1600.00, 'pending_agent',       '2026-06-05 14:00:00'),
(20, 20, 13, 29, 35,  '2026-07-01', '2026-12-31', 'custom',  900.00, 900.00, 'agent_verifying',     '2026-06-07 11:00:00'),
(21, 21, 25, 11, NULL, '2026-08-01', '2027-07-31', '1_year',  850.00, 850.00, 'pending_landlord',    '2026-06-08 09:00:00'),
(22, 22, 15, 31, NULL, '2026-08-01', '2027-07-31', '1_year', 2200.00,2200.00, 'pending_landlord',    '2026-06-10 10:00:00'),
(23, 24, 23, 33, NULL, '2026-09-01', '2027-08-31', '1_year',  380.00, 380.00, 'pending_landlord',    '2026-06-11 13:00:00');

-- ============================================================================
-- MORE CONTRACTS (8 extra — for active/completed tenancies)
-- ============================================================================
INSERT INTO contracts (id, contract_code, booking_id, student_id, landlord_id, agent_id, property_id, start_date, end_date, monthly_rent, deposit, terms, status, activated_at, student_signed_at, landlord_signed_at, agent_signed_at, created_at) VALUES
(4, 'RB-2026-00004', 9,  19, 10, 15, 11, '2026-02-01', '2027-01-31',  420.00, 420.00, 'Standard 1-year tenancy.', 'completed', '2026-01-25 14:00:00', '2026-01-22 11:00:00', '2026-01-23 10:00:00', '2026-01-25 09:00:00', '2026-01-20 10:00:00'),
(5, 'RB-2026-00005', 10, 20, 12, 16, 16, '2026-02-15', '2027-02-14',  550.00, 550.00, 'Standard 1-year tenancy.', 'active',    '2026-02-10 16:00:00', '2026-02-05 10:00:00', '2026-02-08 11:00:00', '2026-02-10 14:00:00', '2026-02-01 09:00:00'),
(6, 'RB-2026-00006', 11, 21, 10, 15, 12, '2026-03-01', '2027-02-28',  460.00, 460.00, 'Standard 1-year tenancy.', 'active',    '2026-02-25 11:00:00', '2026-02-22 10:00:00', '2026-02-23 14:00:00', '2026-02-25 09:00:00', '2026-02-18 10:00:00'),
(7, 'RB-2026-00007', 12, 22, 29, 16, 14, '2026-03-15', '2027-03-14',  700.00, 700.00, 'Standard 1-year tenancy.', 'active',    '2026-03-10 15:00:00', '2026-03-07 09:00:00', '2026-03-08 11:00:00', '2026-03-10 13:00:00', '2026-03-02 14:00:00'),
(8, 'RB-2026-00008', 13, 23, 31, 18, 20, '2026-04-01', '2026-07-31',  320.00, 320.00, 'Standard 4-month tenancy.','active',    '2026-03-28 16:00:00', '2026-03-25 10:00:00', '2026-03-26 11:00:00', '2026-03-28 14:00:00', '2026-03-22 09:00:00'),
(9, 'RB-2026-00009', 14, 24, 32, 15, 21, '2026-04-15', '2027-04-14', 1100.00,1100.00, 'Standard 1-year tenancy.', 'active',    '2026-04-10 17:00:00', '2026-04-05 11:00:00', '2026-04-08 14:00:00', '2026-04-10 15:00:00', '2026-04-01 13:00:00'),
(10,'RB-2026-00010', 15, 25, 33, 18, 24, '2026-05-01', '2026-08-31',  360.00, 360.00, 'Standard 4-month tenancy.','active',    '2026-04-26 14:00:00', '2026-04-23 09:00:00', '2026-04-24 11:00:00', '2026-04-26 12:00:00', '2026-04-20 10:00:00'),
(11,'RB-2026-00011', 16, 26, 12, 16, 28, '2026-05-15', '2027-05-14', 1050.00,1050.00, 'Standard 1-year tenancy.', 'active',    '2026-05-10 18:00:00', '2026-05-05 14:00:00', '2026-05-08 11:00:00', '2026-05-10 16:00:00', '2026-04-30 12:00:00');

-- ============================================================================
-- MORE COMMISSIONS (for chart visualization of revenue)
-- ============================================================================
INSERT INTO agent_commissions (contract_id, agent_id, base_rent, commission_pct, commission_amt, sst_pct, sst_amt, total_payable, status, earned_at, released_at, paid_at) VALUES
(4,  15,  420.00, 100.00,  420.00, 6.00,  25.20,  445.20, 'paid',    '2026-01-25 14:00:00', '2026-02-01 10:00:00', '2026-02-10 09:00:00'),
(5,  16,  550.00, 100.00,  550.00, 6.00,  33.00,  583.00, 'paid',    '2026-02-10 16:00:00', '2026-02-15 09:00:00', '2026-02-25 10:00:00'),
(6,  15,  460.00, 100.00,  460.00, 6.00,  27.60,  487.60, 'paid',    '2026-02-25 11:00:00', '2026-03-01 09:00:00', '2026-03-10 11:00:00'),
(7,  16,  700.00, 100.00,  700.00, 6.00,  42.00,  742.00, 'paid',    '2026-03-10 15:00:00', '2026-03-15 09:00:00', '2026-03-25 10:00:00'),
(8,  18,  320.00, 100.00,  320.00, 6.00,  19.20,  339.20, 'paid',    '2026-03-28 16:00:00', '2026-04-01 09:00:00', '2026-04-10 10:00:00'),
(9,  15, 1100.00, 100.00, 1100.00, 6.00,  66.00, 1166.00, 'paid',    '2026-04-10 17:00:00', '2026-04-15 09:00:00', '2026-04-25 10:00:00'),
(10, 18,  360.00, 100.00,  360.00, 6.00,  21.60,  381.60, 'released','2026-04-26 14:00:00', '2026-05-01 10:00:00', NULL),
(11, 16, 1050.00, 100.00, 1050.00, 6.00,  63.00, 1113.00, 'earned',  '2026-05-10 18:00:00', NULL, NULL);

-- ============================================================================
-- VERIFY
-- ============================================================================
SELECT 'Users'         AS table_name, COUNT(*) AS row_count FROM users
UNION ALL SELECT 'Students',      COUNT(*) FROM students
UNION ALL SELECT 'Landlords',     COUNT(*) FROM landlords
UNION ALL SELECT 'Agents',        COUNT(*) FROM agents
UNION ALL SELECT 'Properties',    COUNT(*) FROM properties
UNION ALL SELECT 'Bookings',      COUNT(*) FROM bookings
UNION ALL SELECT 'Contracts',     COUNT(*) FROM contracts
UNION ALL SELECT 'Commissions',   COUNT(*) FROM agent_commissions;

-- EXPECTED:
--   Users:       35  (admin + 18 students + 10 landlords + 6 agents)
--   Students:    18
--   Landlords:   10
--   Agents:      6
--   Properties:  30
--   Bookings:    23
--   Contracts:   11
--   Commissions: 11

SET FOREIGN_KEY_CHECKS = 1;

-- Sample co-tenancy posts
INSERT INTO co_tenancy_posts (poster_id, property_id, message, housemates_needed, created_at) VALUES
(20, 4, 'Looking for 2 quiet female housemates for this 3BR. I''m Year 3 CS, non-smoker. Move-in Aug 1.', 2, '2026-06-05 09:00:00'),
(22, 15, 'Found this 3-bed apartment near UTeM, too pricey alone. Need 2 more housemates, prefer engineering students.', 2, '2026-06-08 11:30:00'),
(26, 10, 'Whole single-storey house, perfect for 3 students. I''m friendly, like cooking. Move-in flexible.', 2, '2026-06-10 14:00:00'),
(24, 21, 'Premium loft studio in city center but expensive alone. Looking for 1 housemate to share. Year 4 student.', 1, '2026-06-09 16:00:00');






--Change pswd to Test@123
UPDATE `users` SET password_hash = '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u' WHERE primary_role != 'admin';