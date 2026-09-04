-- E2E fixture accounts + pre-seeded properties for the Playwright flow specs
-- (tests/flow1..6-*.spec.js / tests/helpers/auth.js). These accounts do not
-- exist in dbrb_2026.sql or seed_data.sql, so the suite cannot log in without
-- them. Safe to re-run (INSERT IGNORE). Password for all accounts: Test@1234
-- Hash below is bcrypt('Test@1234').

SET FOREIGN_KEY_CHECKS=0;

-- users -----------------------------------------------------------------
INSERT IGNORE INTO users (id, email, password_hash, primary_role, status, last_used_role) VALUES
  (274, 'll@test.com',    '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'landlord', 'active', 'landlord'),
  (275, 'agt@test.com',   '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'agent',    'active', 'agent'),
  (276, 'admin@test.com', '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'admin',    'active', 'admin'),
  (277, 's1@test.com',    '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'student',  'active', 'student'),
  (278, 's2@test.com',    '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'student',  'active', 'student'),
  (279, 's3@test.com',    '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'student',  'active', 'student'),
  (280, 's4@test.com',    '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'student',  'active', 'student'),
  (281, 's5@test.com',    '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'student',  'active', 'student'),
  (282, 's6@test.com',    '$2y$10$jF4z7XNCwd6VMBEhT/JqAOD5WZTFpjjv/TJCWvnKxVMmW/BfvNWvy', 'student',  'active', 'student');

-- user_roles (multi-role membership — mirrors primary_role above) ------
INSERT IGNORE INTO user_roles (user_id, role, is_primary) VALUES
  (274, 'landlord', 1),
  (275, 'agent',    1),
  (276, 'admin',    1),
  (277, 'student',  1),
  (278, 'student',  1),
  (279, 'student',  1),
  (280, 'student',  1),
  (281, 'student',  1),
  (282, 'student',  1);

-- role subtype rows ------------------------------------------------------
INSERT IGNORE INTO landlords (user_id, full_name, preferred_name, ic_no, phone, verified) VALUES
  (274, 'Encik Roslan', 'Roslan', '700101-14-9001', '019-1110001', 1);

INSERT IGNORE INTO agents (user_id, full_name, preferred_name, staff_id, department, phone, availability) VALUES
  (275, 'Agent Siti', 'Siti', 'STAFF-E2E01', 'Student Housing Office', '019-1110002', 'available');

INSERT IGNORE INTO students (user_id, full_name, preferred_name, matric_no, ic_no, phone, gender) VALUES
  (277, 'Ahmad Faris',    'Faris',  'B032410E01', '021103-14-5678', '011-2345001', 'male'),
  (278, 'Lim Wei Xian',   'Wei',    'B032410E02', '021205-10-1234', '012-3456002', 'male'),
  (279, 'Priya Nair',     'Priya',  'B032410E03', '021308-07-9876', '013-9876003', 'female'),
  (280, 'Nurul Ain',      'Ain',    'B032410E04', '021401-05-4321', '014-1234004', 'female'),
  (281, 'Tan Jia Hui',    'Jia',    'B032410E05', '021502-03-8765', '015-5678005', 'female'),
  (282, 'Hafiz Zulkifli', 'Hafiz',  'B032410E06', '021603-01-2109', '016-9012006', 'male');

-- admin has no subtype table.

-- pre-seeded approved Whole Unit properties (flow2 / flow3 pre-conditions) ---
-- assigned_agent_id must be set alongside agent_status='accepted', or
-- property.php disables the chat button ("Agent not yet verified").
INSERT IGNORE INTO properties
  (id, landlord_id, title, property_type, address, city, postcode, state,
   monthly_rent, deposit, description, furnishing, status, agent_status, assigned_agent_id)
VALUES
  (9001, 274, 'E2E Whole Unit — Taman Melaka Raya', 'whole_unit',
   'No. 1, Jalan Merdeka', 'Melaka Tengah', '75000', 'Melaka',
   900.00, 1800.00, 'Pre-seeded fixture for E2E flow 2 (student e-sign).',
   'full', 'available', 'accepted', 275),
  (9002, 274, 'E2E Whole Unit — Bukit Beruang', 'whole_unit',
   'No. 2, Jalan Harmoni', 'Bukit Beruang', '75450', 'Melaka',
   950.00, 1900.00, 'Pre-seeded fixture for E2E flow 3 (3-tenant wet-sign).',
   'full', 'available', 'accepted', 275),
  -- flow5 (agent inspection) pre-conditions: pending_approval + agent_status=pending
  (9010, 274, 'E2E Room — Durian Tunggal (for inspection)', 'room',
   'No. 10, Jalan Inspek', 'Durian Tunggal', '76100', 'Melaka',
   380.00, 760.00, 'Pre-seeded fixture for E2E flow 5 (agent accepts + approves).',
   'partial', 'pending_approval', 'pending', 275),
  (9011, 274, 'E2E Room — Batu Berendam (for rejection)', 'room',
   'No. 11, Jalan Tolak', 'Batu Berendam', '75350', 'Melaka',
   400.00, 800.00, 'Pre-seeded fixture for E2E flow 5 (agent rejects, separate property).',
   'partial', 'pending_approval', 'pending', 275);

SET FOREIGN_KEY_CHECKS=1;
