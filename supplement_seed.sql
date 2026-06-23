-- ============================================================
-- supplement_seed.sql
-- Run AFTER seed_data.sql to cover all missing demo scenarios.
-- Safe to re-run (INSERT IGNORE throughout).
-- ============================================================
SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

-- Ensure semesters_needed column exists (from migration add_cotenancy_semesters.sql)
ALTER TABLE `co_tenancy_posts`
    ADD COLUMN IF NOT EXISTS `semesters_needed` TINYINT UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'How many semesters the poster intends to rent (1-6)'
    AFTER `housemates_needed`;

-- ============================================================
-- SECTION 1: New properties for missing booking statuses
-- All reference existing landlord/agent IDs from seed.
-- Property IDs 139-148 (max in seed is 138).
-- ============================================================

-- 139: available, whole unit — pending_agent booking
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (139,241,'Cheng Whole Unit #139','whole_unit','No. 1, Persiaran Teknologi, Cheng, Melaka','Cheng','75250','Melaka','2.2300000','102.2500000','https://maps.google.com/?q=2.2300000,102.2500000',1200.00,2400.00,'Spacious 4-room whole unit. Near UTeM shuttle bus. Gated community.','WiFi,Air Conditioning,Washing Machine,Refrigerator,Kitchen,Parking','partial','available',34,'2026-05-01 00:00:00','accepted','agent_led','2026-05-05 00:00:00',34,'2026-04-28 00:00:00','2026-05-05 00:00:00');

-- 140: available, room — agent_assigned booking
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (140,242,'Ayer Keroh Room #140','room','No. 15, Taman Seri Melaka, Ayer Keroh, Melaka','Ayer Keroh','75450','Melaka','2.2650000','102.2830000','https://maps.google.com/?q=2.2650000,102.2830000',380.00,760.00,'Single room, private bathroom. Quiet neighbourhood.','WiFi,Air Conditioning,Wardrobe,Study Table','full','available',35,'2026-05-03 00:00:00','accepted','either','2026-05-07 00:00:00',35,'2026-04-30 00:00:00','2026-05-07 00:00:00');

-- 141: available, studio — agent_verified booking
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (141,243,'Bukit Beruang Studio #141','studio','No. 22, Jalan Bestari, Bukit Beruang, Melaka','Bukit Beruang','75450','Melaka','2.2480000','102.2750000','https://maps.google.com/?q=2.2480000,102.2750000',680.00,1360.00,'Modern studio with built-in kitchen. Walking distance to UTeM.','WiFi,Air Conditioning,Washing Machine,Refrigerator','full','available',15,'2026-05-05 00:00:00','accepted','agent_led','2026-05-09 00:00:00',15,'2026-05-02 00:00:00','2026-05-09 00:00:00');

-- 142: available, room — inspection_aborted booking
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (142,244,'Durian Tunggal Room #142','room','No. 8, Lorong Permai 2, Durian Tunggal, Melaka','Durian Tunggal','76100','Melaka','2.5020000','102.2010000','https://maps.google.com/?q=2.5020000,102.2010000',320.00,640.00,'Affordable room, shared bathroom. Near UTeM bus stop.','WiFi,Fan,Wardrobe,Kitchen','unfurnished','available',16,'2026-05-07 00:00:00','accepted','landlord_led','2026-05-11 00:00:00',16,'2026-05-04 00:00:00','2026-05-11 00:00:00');

-- 143: available, studio — cancelled_by_admin booking
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (143,245,'Melaka Tengah Studio #143','studio','No. 33, Jalan Tun Perak, Melaka Tengah, Melaka','Melaka Tengah','75300','Melaka','2.2000000','102.2450000','https://maps.google.com/?q=2.2000000,102.2450000',720.00,1440.00,'Studio with city view. Good transport links.','WiFi,Air Conditioning,Refrigerator','partial','available',34,'2026-05-09 00:00:00','accepted','either','2026-05-13 00:00:00',34,'2026-05-06 00:00:00','2026-05-13 00:00:00');

-- 144: BOOKED (status=booked), whole unit — KEY DEMO: contract_pending with co_tenants
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (144,246,'Ayer Keroh Whole Unit #144','whole_unit','No. 4, Persiaran Harmoni, Ayer Keroh, Melaka','Ayer Keroh','75450','Melaka','2.2720000','102.2870000','https://maps.google.com/?q=2.2720000,102.2870000',1400.00,2800.00,'Premium 4-room unit near UTeM. Each room has A/C. 4 students sharing.','WiFi,Air Conditioning,Washing Machine,Refrigerator,Kitchen,Parking,Water Heater','full','booked',35,'2026-04-20 00:00:00','accepted','agent_led','2026-04-25 00:00:00',35,'2026-04-17 00:00:00','2026-06-10 00:00:00');

-- 145: HIDDEN — landlord temporarily hid listing
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (145,247,'Cheng Studio #145','studio','No. 7, Jalan Harmoni, Cheng, Melaka','Cheng','75250','Melaka','2.2310000','102.2530000','https://maps.google.com/?q=2.2310000,102.2530000',650.00,1300.00,'Studio near MBO Cinema and Giant. Good for working students.','WiFi,Air Conditioning,Washing Machine','partial','hidden',15,'2026-03-01 00:00:00','accepted','either','2026-03-05 00:00:00',15,'2026-02-26 00:00:00','2026-05-01 00:00:00');

-- 146: available — REJECTED then RELISTED (two assignment rounds, shows FIFO reassignment)
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (146,248,'Bukit Baru Room #146','room','No. 12, Taman Bukit Baru, Bukit Baru, Melaka','Ayer Keroh','75450','Melaka','2.2500000','102.2600000','https://maps.google.com/?q=2.2500000,102.2600000',350.00,700.00,'Single room in terrace house. Clean and safe neighbourhood.','WiFi,Fan,Wardrobe,Kitchen','unfurnished','available',16,'2026-04-10 00:00:00','accepted','landlord_led','2026-04-14 00:00:00',16,'2026-04-07 00:00:00','2026-04-14 00:00:00');

-- 147: PENDING_APPROVAL — agent assigned ~23h ago (near 24h timeout, admin attention row #1)
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (147,249,'Ayer Keroh Room #147','room','No. 3, Lorong Seri Kenangan, Ayer Keroh, Melaka','Ayer Keroh','75450','Melaka','2.2660000','102.2815000','https://maps.google.com/?q=2.2660000,102.2815000',310.00,620.00,'Basic room near public transport. Budget-friendly.','Fan,Wardrobe,Kitchen','unfurnished','pending_approval',34,DATE_SUB(NOW(), INTERVAL 23 HOUR),NULL,'either',NULL,NULL,'2026-06-21 00:00:00','2026-06-21 00:00:00');

-- 148: PENDING_APPROVAL — first agent timed out, second pending (admin attention row #2)
INSERT IGNORE INTO properties (id,landlord_id,title,property_type,address,city,postcode,state,latitude,longitude,maps_url,monthly_rent,deposit,description,facilities,furnishing,status,assigned_agent_id,agent_assigned_at,agent_status,viewing_mode,agent_verified_at,agent_verified_by,created_at,updated_at)
VALUES (148,250,'Durian Tunggal Studio #148','studio','No. 9, Persiaran Bestari, Durian Tunggal, Melaka','Durian Tunggal','76100','Melaka','2.5030000','102.2020000','https://maps.google.com/?q=2.5030000,102.2020000',580.00,1160.00,'Compact studio. 5 min walk to bus stop. Suitable for 1 student.','WiFi,Air Conditioning,Refrigerator','partial','pending_approval',35,DATE_SUB(NOW(), INTERVAL 20 HOUR),NULL,'agent_led',NULL,NULL,'2026-06-21 06:00:00','2026-06-21 06:00:00');

-- ============================================================
-- SECTION 2: Property Agent Assignments — multi-round + rejected
-- ============================================================

-- Props 139-145: single accepted rounds
INSERT IGNORE INTO property_agent_assignments (id,property_id,agent_id,round_number,assigned_at,responded_at,outcome) VALUES
(98, 139,34,1,'2026-05-01 08:00:00','2026-05-01 10:00:00','accepted'),
(99, 140,35,1,'2026-05-03 08:00:00','2026-05-03 10:00:00','accepted'),
(100,141,15,1,'2026-05-05 08:00:00','2026-05-05 10:00:00','accepted'),
(101,142,16,1,'2026-05-07 08:00:00','2026-05-07 10:00:00','accepted'),
(102,143,34,1,'2026-05-09 08:00:00','2026-05-09 10:00:00','accepted'),
(103,144,35,1,'2026-04-20 08:00:00','2026-04-20 10:00:00','accepted'),
(104,145,15,1,'2026-03-01 08:00:00','2026-03-01 10:00:00','accepted');

-- Prop 146: round 1 REJECTED (incomplete documents), round 2 accepted — shows FIFO reassignment
INSERT IGNORE INTO property_agent_assignments (id,property_id,agent_id,round_number,assigned_at,responded_at,outcome,rejection_reason) VALUES
(105,146,34,1,'2026-03-10 08:00:00','2026-03-10 14:00:00','rejected','Property documents incomplete — Surat Hakmilik missing. Landlord resubmitted after correction.');
INSERT IGNORE INTO property_agent_assignments (id,property_id,agent_id,round_number,assigned_at,responded_at,outcome) VALUES
(106,146,16,2,'2026-04-07 08:00:00','2026-04-07 12:00:00','accepted');

-- Prop 147: near-timeout, single round pending
INSERT IGNORE INTO property_agent_assignments (id,property_id,agent_id,round_number,assigned_at,responded_at,outcome)
VALUES (107,147,34,1,DATE_SUB(NOW(), INTERVAL 23 HOUR),NULL,'pending');

-- Prop 148: first agent timed out, second agent pending (visible in admin queue)
INSERT IGNORE INTO property_agent_assignments (id,property_id,agent_id,round_number,assigned_at,responded_at,outcome) VALUES
(108,148,15,1,DATE_SUB(NOW(), INTERVAL 26 HOUR),NULL,'timeout'),
(109,148,35,2,DATE_SUB(NOW(), INTERVAL 20 HOUR),NULL,'pending');

-- ============================================================
-- SECTION 3: Bookings — missing statuses (155-160)
-- ============================================================

-- 155: pending_agent — landlord approved, agent hasn't yet taken the case
INSERT IGNORE INTO bookings (id,student_id,property_id,landlord_id,agent_id,start_date,end_date,duration_type,monthly_rent,deposit,status,signed_contract_path,signed_uploaded_at,signed_uploaded_by,student_note,landlord_response,cancellation_reason,cancelled_by,rejected_agents,created_at,updated_at)
VALUES (155,231,139,241,34,'2026-08-01','2027-01-31','1_semester',1200.00,2400.00,'pending_agent',NULL,NULL,NULL,'Quiet student, non-smoker. Available from August 2026.','Approved. Looking forward to having you here.',NULL,NULL,NULL,'2026-06-18 10:00:00','2026-06-19 08:00:00');

-- 156: agent_assigned — agent acknowledged the booking, will schedule verification
INSERT IGNORE INTO bookings (id,student_id,property_id,landlord_id,agent_id,start_date,end_date,duration_type,monthly_rent,deposit,status,signed_contract_path,signed_uploaded_at,signed_uploaded_by,student_note,landlord_response,cancellation_reason,cancelled_by,rejected_agents,created_at,updated_at)
VALUES (156,232,140,242,35,'2026-08-02','2027-02-01','1_semester',380.00,760.00,'agent_assigned',NULL,NULL,NULL,'Final year student. Clean and responsible.','Approved. Agent will contact you soon.',NULL,NULL,NULL,'2026-06-10 09:00:00','2026-06-12 11:00:00');

-- 157: agent_verified — inspection passed, agent approved, contract not yet generated
INSERT IGNORE INTO bookings (id,student_id,property_id,landlord_id,agent_id,start_date,end_date,duration_type,monthly_rent,deposit,status,signed_contract_path,signed_uploaded_at,signed_uploaded_by,student_note,landlord_response,cancellation_reason,cancelled_by,rejected_agents,created_at,updated_at)
VALUES (157,233,141,243,15,'2026-08-03','2027-02-02','1_semester',680.00,1360.00,'agent_verified',NULL,NULL,NULL,'Engineering student. Non-smoker, no pets.','Welcome aboard!',NULL,NULL,NULL,'2026-06-05 10:00:00','2026-06-15 14:00:00');

-- 158: inspection_aborted — inspection cancelled, landlord uncontactable
INSERT IGNORE INTO bookings (id,student_id,property_id,landlord_id,agent_id,start_date,end_date,duration_type,monthly_rent,deposit,status,signed_contract_path,signed_uploaded_at,signed_uploaded_by,student_note,landlord_response,cancellation_reason,cancelled_by,rejected_agents,created_at,updated_at)
VALUES (158,234,142,244,16,'2026-08-04','2027-02-03','1_semester',320.00,640.00,'inspection_aborted',NULL,NULL,NULL,'Looking for budget accommodation near UTeM bus stop.','Approved.','Landlord uncontactable on scheduled inspection day. Property access could not be arranged. Booking placed on hold.',NULL,NULL,'2026-06-01 09:00:00','2026-06-08 16:00:00');

-- 159: cancelled_by_admin — admin intervened after fraud report flagged property
INSERT IGNORE INTO bookings (id,student_id,property_id,landlord_id,agent_id,start_date,end_date,duration_type,monthly_rent,deposit,status,signed_contract_path,signed_uploaded_at,signed_uploaded_by,student_note,landlord_response,cancellation_reason,cancelled_by,rejected_agents,created_at,updated_at)
VALUES (159,59,143,245,34,'2026-08-05','2027-02-04','1_semester',720.00,1440.00,'cancelled_by_admin',NULL,NULL,NULL,'Year 2 student. Prefer quiet environment.','Approved.','Cancelled by admin: fraudulent property documents detected during secondary verification. Landlord account suspended pending investigation.',1,'[]','2026-05-20 10:00:00','2026-06-02 09:00:00');

-- 160: KEY DEMO ROW — contract_pending, whole unit, co-tenants submitted, pending contract generation
INSERT IGNORE INTO bookings (id,student_id,property_id,landlord_id,agent_id,start_date,end_date,duration_type,monthly_rent,deposit,status,signed_contract_path,signed_uploaded_at,signed_uploaded_by,student_note,landlord_response,cancellation_reason,cancelled_by,rejected_agents,created_at,updated_at)
VALUES (160,235,144,246,35,'2026-08-10','2027-02-09','1_semester',1400.00,2800.00,'contract_pending',NULL,NULL,NULL,'Primary tenant. 3 friends joining. All Year 3 UTeM students. Non-smokers.','Approved. Agent has verified everything. Ready for contract.',NULL,NULL,NULL,'2026-06-01 10:00:00','2026-06-20 14:00:00');

-- ============================================================
-- SECTION 4: co_tenants for booking 160 (KEY DEMO ROW)
-- 4-pax whole unit: primary + 3 co-tenants, all pending signature
-- ============================================================

INSERT IGNORE INTO co_tenants (id,booking_id,student_id,is_primary,full_name,ic_number,phone,email,home_address,sign_order,signed_at,signature_data,added_at,added_by,status,notes)
VALUES
(1,160,235,1,'Ong Zi Yang','030000235-99-0235','012-0123245','zi.yang.ong1@student.utem.edu.my','No. 5, Jalan Maju, Ayer Keroh, 75450 Melaka',1,NULL,NULL,'2026-06-15 10:00:00',235,'pending','Primary tenant and lease holder.'),
(2,160,234,0,'Nadzmi bin Abdullah','020000234-99-0234','011-8888678','nadzmi.bdullah2@student.utem.edu.my','No. 2, Lorong Indah, Durian Tunggal, 76100 Melaka',2,NULL,NULL,'2026-06-15 10:00:00',235,'pending',NULL),
(3,160,233,0,'Ridhwan binti Latif','020000233-99-0233','019-7654111','ridhwan.atif2@student.utem.edu.my','No. 8, Jalan Cahaya, Melaka Tengah, 75300 Melaka',3,NULL,NULL,'2026-06-15 10:00:00',235,'pending',NULL),
(4,160,NULL,0,'Ahmad Faris bin Rashid','960000999-01-0001','011-2222345','faris.rashid@gmail.com','No. 15, Jalan Wawasan, Cheng, 75250 Melaka',4,NULL,NULL,'2026-06-15 10:00:00',235,'pending','External co-tenant, not a UTeM student.');

-- Also add co_tenants for 2 existing active Sem3 bookings (shows feature is used system-wide)
-- Booking 151: student 200, property 34, whole unit (Sem3 active)
INSERT IGNORE INTO co_tenants (id,booking_id,student_id,is_primary,full_name,ic_number,phone,email,home_address,sign_order,signed_at,signature_data,added_at,added_by,status,notes)
VALUES
(5,151,200,1,'Arif bin Hassan','030000200-99-0200','013-6913400','arif.assan1@student.utem.edu.my','No. 3, Jalan Sejahtera, Ayer Keroh, 75450 Melaka',1,'2025-01-29 12:00:00',NULL,'2025-01-15 10:00:00',200,'signed','Primary tenant.'),
(6,151,201,0,'Zikri bin Ibrahim','030000201-99-0201','015-9382534','zikri.brahim1@student.utem.edu.my','No. 7, Lorong Bahagia, Melaka Tengah, 75300 Melaka',2,'2025-01-29 13:00:00',NULL,'2025-01-15 10:00:00',200,'signed',NULL),
(7,151,202,0,'Irfan binti Yusof','030000202-99-0202','016-0617101','irfan.usof1@student.utem.edu.my','No. 11, Jalan Aman, Durian Tunggal, 76100 Melaka',3,NULL,NULL,'2025-01-15 10:00:00',200,'pending',NULL);

-- ============================================================
-- SECTION 5: co_tenancy_posts — partner matching (Sem 3)
-- ============================================================

INSERT IGNORE INTO co_tenancy_posts (id,poster_id,property_id,title,message,housemates_needed,semesters_needed,status,group_conversation_id,created_at,updated_at) VALUES
(1,225,103,'2 spots left — whole unit Durian Tunggal','Hi! Year 3 Software Engineering student here. I have a whole unit in Durian Tunggal, rent split across 3 pax = RM315 each. Looking for 2 non-smoker housemates. Available from August 2026.',2,2,'open',13,'2026-06-01 10:00:00','2026-06-09 14:00:00'),
(2,226,95,'Studio in Ayer Keroh — 1 housemate wanted','Looking for a female student to share my studio. RM500 split = RM250 each. Near UTeM shuttle bus stop. Year 2 Business student. Morning person.',1,1,'open',NULL,'2026-06-03 14:00:00','2026-06-03 14:00:00'),
(3,227,144,'4-room whole unit in Ayer Keroh — 3 spots left!','Big whole unit near UTeM. 4 rooms, each with A/C. I have 1 friend coming so looking for 2 more. RM1400 total = RM350 per person. Group chat created when full.',3,2,'open',NULL,'2026-06-05 09:00:00','2026-06-05 09:00:00'),
(4,228,100,'Room sharing in Melaka Tengah — FILLED','Already found housemates. Closing post.',1,1,'filled',NULL,'2026-06-04 11:00:00','2026-06-10 15:00:00'),
(5,229,101,'2 spots in house near UTeM — Ayer Keroh','3-room terrace house, 2 spots open. Each pays RM295. 5 min walk to UTeM. Malay-Muslim household preferred.',2,2,'open',NULL,'2026-06-07 08:00:00','2026-06-07 08:00:00'),
(6,230,102,'Bukit Beruang room share — CANCELLED','Found someone already.',1,1,'cancelled',NULL,'2026-06-02 09:00:00','2026-06-09 10:00:00');

-- ============================================================
-- SECTION 6: co_tenancy_applications
-- ============================================================

INSERT IGNORE INTO co_tenancy_applications (id,post_id,applicant_id,message,status,created_at,responded_at) VALUES
-- Applications to Post 1 (student 225, prop 103)
(1,1,231,'Hi! Year 3 Electrical Engineering. Non-smoker, tidy, usually home evenings. Happy to split chores. Available from August.','pending','2026-06-05 11:00:00',NULL),
(2,1,232,'Final year student, quiet, no visitors after 10pm. Looking for stable accommodation.','accepted','2026-06-06 14:00:00','2026-06-08 10:00:00'),
(3,1,233,'Year 2 student, clean and organised. Part-time job on weekends only.','rejected','2026-06-07 09:00:00','2026-06-08 10:30:00'),
-- Applications to Post 3 (student 227, prop 144)
(4,3,234,'Year 3 Computer Science. Non-smoker, vegetarian. Quiet and responsible housemate.','accepted','2026-06-08 10:00:00','2026-06-10 09:00:00'),
(5,3,236,'Sem 3 student looking for accommodation near UTeM. Friendly and clean.','pending','2026-06-10 08:00:00',NULL),
-- Applications to Post 5 (student 229, prop 101)
(6,5,237,'Malay, Muslim, non-smoker, no late nights. Budget around RM300. Serious applicant.','pending','2026-06-09 14:00:00',NULL);

-- ============================================================
-- SECTION 7: saved_properties — Sem 3 students actively browsing
-- ============================================================

INSERT IGNORE INTO saved_properties (id,user_id,property_id,saved_at) VALUES
(1, 186,86, '2026-06-01 10:00:00'),
(2, 187,87, '2026-06-02 11:00:00'),
(3, 188,88, '2026-06-03 14:00:00'),
(4, 189,86, '2026-06-04 09:00:00'),
(5, 190,90, '2026-06-05 15:00:00'),
(6, 191,91, '2026-06-06 10:00:00'),
(7, 192,92, '2026-06-07 11:00:00'),
(8, 193,93, '2026-06-08 08:00:00'),
(9, 194,139,'2026-06-10 12:00:00'),
(10,195,140,'2026-06-11 16:00:00'),
(11,196,141,'2026-06-12 09:00:00'),
(12,197,144,'2026-06-14 10:00:00');

-- ============================================================
-- SECTION 8: Conversations — all context_types + locked example
-- Conv IDs 8-13 (original DB has 7 from existing data)
-- ============================================================

-- 8: property_inquiry — student 186 enquiring about prop 86 from landlord 251
INSERT IGNORE INTO conversations (id,user_a,user_b,property_id,booking_id,context_type,last_message_at,last_message_preview,last_sender_id,is_locked,locked_reason,created_at)
VALUES (8,186,251,86,NULL,'property_inquiry','2026-06-10 14:00:00','That would be great! I am free on Saturday afternoon.',186,0,NULL,'2026-06-10 13:00:00');

-- 9: booking — student 186 and agent 16, booking 91
INSERT IGNORE INTO conversations (id,user_a,user_b,property_id,booking_id,context_type,last_message_at,last_message_preview,last_sender_id,is_locked,locked_reason,created_at)
VALUES (9,186,16,86,91,'booking','2026-06-15 10:00:00','Your move-in inspection is scheduled for 20 June.',16,0,NULL,'2026-06-05 09:00:00');

-- 10: agent_case — agent 34 and landlord 241, property 139
INSERT IGNORE INTO conversations (id,user_a,user_b,property_id,booking_id,context_type,last_message_at,last_message_preview,last_sender_id,is_locked,locked_reason,created_at)
VALUES (10,34,241,139,155,'agent_case','2026-06-19 11:00:00','I have accepted the case. Please confirm the inspection date.',34,0,NULL,'2026-06-18 09:00:00');

-- 11: contract_prep — agent 35 and student 235, booking 160 (KEY DEMO)
INSERT IGNORE INTO conversations (id,user_a,user_b,property_id,booking_id,context_type,last_message_at,last_message_preview,last_sender_id,is_locked,locked_reason,created_at)
VALUES (11,35,235,144,160,'contract_prep','2026-06-20 14:00:00','Tenant form received. Generating contract shortly.',35,0,NULL,'2026-06-15 10:00:00');

-- 12: booking — LOCKED (completed booking archived)
INSERT IGNORE INTO conversations (id,user_a,user_b,property_id,booking_id,context_type,last_message_at,last_message_preview,last_sender_id,is_locked,locked_reason,created_at)
VALUES (12,120,237,50,152,'booking','2025-02-01 10:00:00','Thanks for everything, it was a great experience!',120,1,'Booking completed — conversation archived.','2024-08-10 09:00:00');

-- 13: housemate_group — group chat for co-tenancy post 1
INSERT IGNORE INTO conversations (id,user_a,user_b,property_id,booking_id,context_type,last_message_at,last_message_preview,last_sender_id,is_locked,locked_reason,created_at)
VALUES (13,225,NULL,103,NULL,'housemate_group','2026-06-09 15:00:00','Welcome to the group! One more spot left.',225,0,NULL,'2026-06-09 14:00:00');

-- conversation_participants for the housemate group (conv 13)
INSERT IGNORE INTO conversation_participants (id,conversation_id,user_id,joined_at) VALUES
(1,13,225,'2026-06-09 14:00:00'),
(2,13,232,'2026-06-09 14:00:00');

-- ============================================================
-- SECTION 9: Messages — rebuilt using quick reply phrases
-- Quick replies per role:
--   student:         'Hi, is this still available?' | 'Can I view it?' | 'What\'s included in the rent?' | 'Is the deposit negotiable?'
--   landlord:        'Yes, still available.' | 'When would you like to view?' | 'Rent includes WiFi and water.' | 'Let me check and get back to you.'
--   agent:           'I can arrange an inspection.' | 'Let me schedule a viewing.' | 'Inspection report is ready.' | 'Please proceed to sign the contract.'
--   housemate_group: 'Hi everyone!' | 'When can we meet to discuss?' | 'Sounds good to me.' | 'Let me check and get back.'
-- ============================================================

-- conv 8: property_inquiry — student 186 ↔ landlord 251
INSERT IGNORE INTO messages (id,conversation_id,sender_id,body,message_type,metadata,sent_at,read_at) VALUES
(1,8,186,'Hi, is this still available?','text',NULL,'2026-06-10 13:05:00','2026-06-10 13:20:00'),
(2,8,251,'Yes, still available.','text',NULL,'2026-06-10 13:20:00','2026-06-10 13:30:00'),
(3,8,186,'Can I view it?','text',NULL,'2026-06-10 13:30:00','2026-06-10 14:00:00'),
(4,8,251,'When would you like to view?','text',NULL,'2026-06-10 14:00:00','2026-06-10 14:30:00'),
(5,8,186,'What\'s included in the rent?','text',NULL,'2026-06-10 14:30:00','2026-06-10 15:00:00'),
(6,8,251,'Rent includes WiFi and water.','text',NULL,'2026-06-10 15:00:00','2026-06-10 15:30:00'),
(7,8,186,'Is the deposit negotiable?','text',NULL,'2026-06-10 15:30:00',NULL);

-- conv 9: booking — student 186 ↔ agent 16
INSERT IGNORE INTO messages (id,conversation_id,sender_id,body,message_type,metadata,sent_at,read_at) VALUES
(8,9,1,'Booking #91 created. Agent 16 has been assigned to manage this case.','system_notice','{"booking_id":91}','2026-06-05 09:01:00','2026-06-05 09:30:00'),
(9,9,16,'I can arrange an inspection.','text',NULL,'2026-06-05 09:30:00','2026-06-05 10:00:00'),
(10,9,186,'Can I view it?','text',NULL,'2026-06-05 10:00:00','2026-06-05 10:05:00'),
(11,9,16,'Let me schedule a viewing.','text',NULL,'2026-06-05 10:05:00','2026-06-05 11:00:00'),
(12,9,16,'Inspection report is ready.','text',NULL,'2026-06-15 09:00:00','2026-06-15 09:30:00'),
(13,9,16,'Please proceed to sign the contract.','text',NULL,'2026-06-15 09:35:00',NULL);

-- conv 10: agent_case — agent 34 ↔ landlord 241
INSERT IGNORE INTO messages (id,conversation_id,sender_id,body,message_type,metadata,sent_at,read_at) VALUES
(14,10,1,'Agent 34 has accepted property #139 and will now conduct the listing inspection.','system_notice','{"property_id":139,"agent_id":34}','2026-06-18 09:01:00','2026-06-18 09:10:00'),
(15,10,34,'I can arrange an inspection.','text',NULL,'2026-06-18 09:10:00','2026-06-18 11:00:00'),
(16,10,241,'When would you like to view?','text',NULL,'2026-06-18 11:00:00','2026-06-18 11:05:00'),
(17,10,34,'Let me schedule a viewing.','text',NULL,'2026-06-18 11:05:00','2026-06-18 11:30:00'),
(18,10,241,'Let me check and get back to you.','text',NULL,'2026-06-18 11:30:00',NULL);

-- conv 11: contract_prep — agent 35 ↔ student 235 (KEY DEMO: booking 160)
INSERT IGNORE INTO messages (id,conversation_id,sender_id,body,message_type,metadata,sent_at,read_at) VALUES
(19,11,35,'Inspection report is ready. Your booking for Ayer Keroh Whole Unit #144 has been verified.','text',NULL,'2026-06-15 10:05:00','2026-06-15 10:30:00'),
(20,11,235,'Is the deposit negotiable?','text',NULL,'2026-06-15 10:30:00','2026-06-15 10:31:00'),
(21,11,35,'The deposit is fixed. Please fill in the tenant details form so I can prepare the contract.','text',NULL,'2026-06-15 10:35:00','2026-06-15 11:00:00'),
(22,11,1,'Tenant details submitted by student #235. 3 co-tenants recorded. Contract can now be generated.','system_notice','{"booking_id":160,"co_tenant_count":3}','2026-06-15 14:00:00','2026-06-15 14:05:00'),
(23,11,35,'Please proceed to sign the contract.','text',NULL,'2026-06-20 14:00:00',NULL);

-- conv 12: booking (LOCKED/completed) — student 120 ↔ user 237
INSERT IGNORE INTO messages (id,conversation_id,sender_id,body,message_type,metadata,sent_at,read_at) VALUES
(24,12,120,'Hi, is this still available?','text',NULL,'2024-08-10 09:05:00','2024-08-10 09:30:00'),
(25,12,237,'Yes, still available.','text',NULL,'2024-08-10 09:30:00','2024-08-10 10:00:00'),
(26,12,120,'Is the deposit negotiable?','text',NULL,'2024-08-10 10:00:00','2024-08-10 10:30:00'),
(27,12,237,'Let me check and get back to you.','text',NULL,'2024-08-10 10:30:00','2024-08-10 14:00:00'),
(28,12,120,'Thanks for everything, it was a great experience!','text',NULL,'2025-02-01 10:00:00','2025-02-01 10:05:00');

-- conv 13: housemate_group — students 225 and 232
INSERT IGNORE INTO messages (id,conversation_id,sender_id,body,message_type,metadata,sent_at,read_at) VALUES
(29,13,225,'Hi everyone!','text',NULL,'2026-06-09 14:01:00','2026-06-09 14:30:00'),
(30,13,232,'Hi everyone!','text',NULL,'2026-06-09 14:05:00','2026-06-09 14:30:00'),
(31,13,225,'When can we meet to discuss?','text',NULL,'2026-06-09 14:30:00','2026-06-09 15:00:00'),
(32,13,232,'Sounds good to me.','text',NULL,'2026-06-09 15:00:00','2026-06-09 15:10:00'),
(33,13,225,'Let me check and get back.','text',NULL,'2026-06-09 15:10:00',NULL);

-- Update conversation last_message_preview to match new last messages
UPDATE conversations SET last_message_preview='Is the deposit negotiable?',    last_sender_id=186, last_message_at='2026-06-10 15:30:00' WHERE id=8;
UPDATE conversations SET last_message_preview='Please proceed to sign the contract.', last_sender_id=16,  last_message_at='2026-06-15 09:35:00' WHERE id=9;
UPDATE conversations SET last_message_preview='Let me check and get back to you.',    last_sender_id=241, last_message_at='2026-06-18 11:30:00' WHERE id=10;
UPDATE conversations SET last_message_preview='Please proceed to sign the contract.', last_sender_id=35,  last_message_at='2026-06-20 14:00:00' WHERE id=11;
UPDATE conversations SET last_message_preview='Let me check and get back.',           last_sender_id=225, last_message_at='2026-06-09 15:10:00' WHERE id=13;

-- ============================================================
-- SECTION 10: Move-in Inspections
-- For existing active contracts to show the feature is in use
-- ============================================================

-- Inspection for contract 1 (booking 1, agent 16)
INSERT IGNORE INTO move_in_inspections (id,contract_id,agent_id,inspected_at,inventory_items,overall_notes,student_acknowledged,student_acknowledged_at,landlord_acknowledged,landlord_acknowledged_at)
VALUES (1,1,16,'2024-01-30 10:00:00',
'[{"item":"Master Bedroom","condition":"Good","notes":"Minor paint peeling on one wall"},{"item":"Living Room","condition":"Good","notes":"Sofa has slight discoloration — documented"},{"item":"Kitchen","condition":"Good","notes":"All appliances working"},{"item":"Bathroom","condition":"Good","notes":"No issues found"}]',
'Overall in good condition. Minor cosmetic issues noted and photographed. Student accepted keys.',
1,'2024-01-30 11:30:00',1,'2024-01-30 12:00:00');

-- Inspection for contract 2 (booking 2, agent 34)
INSERT IGNORE INTO move_in_inspections (id,contract_id,agent_id,inspected_at,inventory_items,overall_notes,student_acknowledged,student_acknowledged_at,landlord_acknowledged,landlord_acknowledged_at)
VALUES (2,2,34,'2024-01-31 14:00:00',
'[{"item":"Room 1","condition":"Good","notes":"Freshly painted"},{"item":"Room 2","condition":"Fair","notes":"Wardrobe door hinge loose — landlord to fix"},{"item":"Common Area","condition":"Good","notes":"Clean and spacious"},{"item":"Bathroom","condition":"Good","notes":"New shower head installed"}]',
'Good condition overall. Landlord agreed to fix wardrobe hinge in Room 2 within one week.',
1,'2024-01-31 15:30:00',1,'2024-01-31 16:00:00');

-- Inspection for contract 108 (booking 151 — Sem3 active, student 200, property 34)
INSERT IGNORE INTO move_in_inspections (id,contract_id,agent_id,inspected_at,inventory_items,overall_notes,student_acknowledged,student_acknowledged_at,landlord_acknowledged,landlord_acknowledged_at)
VALUES (3,108,15,'2025-01-30 10:00:00',
'[{"item":"Bedroom 1","condition":"Good","notes":""},{"item":"Bedroom 2","condition":"Good","notes":""},{"item":"Bedroom 3","condition":"Fair","notes":"Ceiling fan needs servicing"},{"item":"Kitchen","condition":"Good","notes":"All appliances working"},{"item":"Bathroom","condition":"Good","notes":""}]',
'Property ready for occupation. Ceiling fan in Bedroom 3 to be serviced by landlord within 3 days.',
1,'2025-01-30 12:00:00',1,'2025-01-30 13:00:00');

-- ============================================================
-- SECTION 11: Images and documents for supplement properties
-- ============================================================

INSERT IGNORE INTO property_images (property_id,image_path,is_primary,uploaded_at) VALUES
(139,'uploads/properties/img1.png',1,'2026-04-28 00:00:00'),
(139,'uploads/properties/img2.png',0,'2026-04-28 00:00:00'),
(140,'uploads/properties/img3.png',1,'2026-04-30 00:00:00'),
(140,'uploads/properties/img4.png',0,'2026-04-30 00:00:00'),
(141,'uploads/properties/img5.png',1,'2026-05-02 00:00:00'),
(141,'uploads/properties/img1.png',0,'2026-05-02 00:00:00'),
(142,'uploads/properties/img2.png',1,'2026-05-04 00:00:00'),
(142,'uploads/properties/img3.png',0,'2026-05-04 00:00:00'),
(143,'uploads/properties/img4.png',1,'2026-05-06 00:00:00'),
(143,'uploads/properties/img5.png',0,'2026-05-06 00:00:00'),
(144,'uploads/properties/img1.png',1,'2026-04-17 00:00:00'),
(144,'uploads/properties/img2.png',0,'2026-04-17 00:00:00'),
(145,'uploads/properties/img3.png',1,'2026-02-26 00:00:00'),
(145,'uploads/properties/img4.png',0,'2026-02-26 00:00:00'),
(146,'uploads/properties/img5.png',1,'2026-04-07 00:00:00'),
(146,'uploads/properties/img1.png',0,'2026-04-07 00:00:00'),
(147,'uploads/properties/img2.png',1,'2026-06-21 00:00:00'),
(148,'uploads/properties/img3.png',1,'2026-06-21 00:00:00');

INSERT IGNORE INTO property_documents (property_id,document_type,file_path,original_name,file_size,mime_type,uploaded_by,uploaded_at,notes) VALUES
(25, 'ownership_proof','uploads/property_docs/doc1.pdf','doc1.pdf',204800,'application/pdf',2,  '2024-01-01 00:00:00',NULL),
(139,'ownership_proof','uploads/property_docs/doc2.pdf','doc2.pdf',204800,'application/pdf',241,'2026-04-28 00:00:00',NULL),
(140,'ownership_proof','uploads/property_docs/doc3.pdf','doc3.pdf',204800,'application/pdf',242,'2026-04-30 00:00:00',NULL),
(141,'ownership_proof','uploads/property_docs/doc4.pdf','doc4.pdf',204800,'application/pdf',243,'2026-05-02 00:00:00',NULL),
(142,'ownership_proof','uploads/property_docs/doc5.pdf','doc5.pdf',204800,'application/pdf',244,'2026-05-04 00:00:00',NULL),
(143,'ownership_proof','uploads/property_docs/doc6.pdf','doc6.pdf',204800,'application/pdf',245,'2026-05-06 00:00:00',NULL),
(144,'ownership_proof','uploads/property_docs/doc7.pdf','doc7.pdf',204800,'application/pdf',246,'2026-04-17 00:00:00',NULL),
(145,'ownership_proof','uploads/property_docs/doc8.pdf','doc8.pdf',204800,'application/pdf',247,'2026-02-26 00:00:00',NULL),
(146,'ownership_proof','uploads/property_docs/doc1.pdf','doc1.pdf',204800,'application/pdf',248,'2026-04-07 00:00:00',NULL),
(147,'ownership_proof','uploads/property_docs/doc2.pdf','doc2.pdf',204800,'application/pdf',249,'2026-06-21 00:00:00',NULL),
(148,'ownership_proof','uploads/property_docs/doc3.pdf','doc3.pdf',204800,'application/pdf',250,'2026-06-21 00:00:00',NULL);

SET FOREIGN_KEY_CHECKS=1;

-- ============================================================
-- SUPPLEMENT COVERAGE SUMMARY
-- Run after seed_data.sql
-- ============================================================
-- Booking statuses added:
--   pending_agent (155), agent_assigned (156), agent_verified (157)
--   inspection_aborted (158), cancelled_by_admin (159)
--   contract_pending + co_tenants (160) ← KEY DEMO ROW
--
-- Property statuses added:
--   booked (144), hidden (145)
--   pending_approval near-timeout x2 (147, 148) ← ADMIN DEMO ROWS
--
-- Agent assignment complexity:
--   FIFO rejected → reassigned (prop 146, rounds 1+2) ← shows reassignment
--   Timeout → second agent pending (prop 148, rounds 1+2)
--   Near-timeout pending (prop 147) ← admin attention during demo
--
-- Co-tenants: booking 160 (4-pax, pending), booking 151 (3-pax, 2 signed)
-- Co-tenancy posts: 6 (open, filled, cancelled mix)
-- Co-tenancy applications: 6 (pending, accepted, rejected mix)
-- Saved properties: 12 rows across Sem 3 students
-- Conversations: all context_types covered (property_inquiry, booking,
--   agent_case, contract_prep, housemate_group) + 1 locked
-- Messages: text + system_notice in multiple conversations
-- Move-in inspections: 3 rows (2 Sem1, 1 Sem3)
--
-- NOT covered (require separate migrations or features):
--   agent_transfer_requests — table does not exist, needs migration
--   User pending/rejected status — agents are staff (no self-sign-up flow)
--   Even agent distribution — requires changing base seed (97 rows, near-even)
-- ============================================================
