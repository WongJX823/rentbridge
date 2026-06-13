-- ============================================================================
-- RentBridge — COMPLETE Database Schema (reset + recreate)
-- Database: dbrb_2026
-- Engine:   InnoDB
-- Charset:  utf8mb4
-- Updated:  June 2026 — Module 11 Phase A (agent workflow) included
-- ============================================================================
--
-- ⚠️  WARNING: This script DROPS all existing tables before recreating them.
--    All data will be lost. Run only on a fresh DB or one you can wipe.
--
-- Total: 18 tables across 4 functional groups:
--   1) Identity:    users, students, landlords, agents
--   2) Inventory:   properties, property_images
--   3) Transaction: bookings, contracts, agent_verifications,
--                   agent_verification_photos, move_in_inspections,
--                   move_in_photos, agent_commissions
--   4) Comms:       notifications, friend_requests, friends,
--                   conversations, messages
--
-- HOW TO USE:
--   1. Open phpMyAdmin → select database `dbrb_2026`
--   2. Click "SQL" tab
--   3. Paste this entire file
--   4. Click "Go"
--   5. You should see "18 tables created" + admin row inserted
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- DROP all tables (reverse dependency order)
-- ============================================================================
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversations;
DROP TABLE IF EXISTS friends;
DROP TABLE IF EXISTS friend_requests;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS agent_commissions;
DROP TABLE IF EXISTS move_in_photos;
DROP TABLE IF EXISTS move_in_inspections;
DROP TABLE IF EXISTS agent_verification_photos;
DROP TABLE IF EXISTS agent_verifications;
DROP TABLE IF EXISTS contracts;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS property_images;
DROP TABLE IF EXISTS properties;
DROP TABLE IF EXISTS agents;
DROP TABLE IF EXISTS landlords;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. USERS — central authentication
-- ============================================================================
CREATE TABLE users (
    id              INT NOT NULL AUTO_INCREMENT,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    primary_role    ENUM('student','landlord','agent','admin') NOT NULL,
    status          ENUM('active','pending','suspended','rejected') NOT NULL DEFAULT 'active',
    last_used_role  ENUM('student','landlord','agent','admin') DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email),
    INDEX idx_role_status (primary_role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. STUDENTS — student profile + housing prefs
-- ============================================================================
CREATE TABLE students (
    user_id                 INT NOT NULL,
    full_name               VARCHAR(150) NOT NULL,
    preferred_name          VARCHAR(50)  NOT NULL DEFAULT '',
    matric_no               VARCHAR(20)  NOT NULL UNIQUE,
    university              VARCHAR(80)  NOT NULL DEFAULT 'UTeM',
    phone                   VARCHAR(20)  NOT NULL,

    looking_for_housing     TINYINT(1) NOT NULL DEFAULT 0,
    housing_pref_city       VARCHAR(80)  DEFAULT NULL,
    housing_pref_max_rent   DECIMAL(8,2) DEFAULT NULL,
    housing_pref_move_in    DATE         DEFAULT NULL,
    housing_bio             VARCHAR(255) DEFAULT NULL,

    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_looking (looking_for_housing)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. LANDLORDS — landlord profile
-- ============================================================================
CREATE TABLE landlords (
    user_id         INT NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    preferred_name  VARCHAR(50)  NOT NULL DEFAULT '',
    ic_no           VARCHAR(20)  NOT NULL UNIQUE,
    phone           VARCHAR(20)  NOT NULL,
    address         VARCHAR(255) DEFAULT NULL,
    verified        TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. AGENTS — UTeM staff witness agents
-- ============================================================================
CREATE TABLE agents (
    user_id             INT NOT NULL,
    full_name           VARCHAR(150) NOT NULL,
    preferred_name      VARCHAR(50)  NOT NULL DEFAULT '',
    staff_id            VARCHAR(20)  NOT NULL UNIQUE,
    department          VARCHAR(80)  NOT NULL,
    phone               VARCHAR(20)  NOT NULL,
    availability        ENUM('available','busy','off_duty') NOT NULL DEFAULT 'available',
    max_caseload        INT NOT NULL DEFAULT 5,
    current_caseload    INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assignment (availability, current_caseload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. PROPERTIES — listings (with viewing mode + verification badge)
-- ============================================================================
CREATE TABLE properties (
    id                  INT NOT NULL AUTO_INCREMENT,
    landlord_id         INT NOT NULL,
    title               VARCHAR(150) NOT NULL,
    property_type       ENUM('room','studio','whole_unit') NOT NULL,
    address             TEXT NOT NULL,
    city                VARCHAR(80) NOT NULL,
    postcode            VARCHAR(10) NOT NULL,
    state               VARCHAR(50) NOT NULL DEFAULT 'Melaka',
    monthly_rent        DECIMAL(8,2) NOT NULL,
    deposit             DECIMAL(8,2) NOT NULL DEFAULT 0,
    description         TEXT DEFAULT NULL,
    facilities          TEXT DEFAULT NULL,
    furnishing          ENUM('none','partial','full') NOT NULL DEFAULT 'partial',
    status              ENUM('pending_approval','available','booked','rented','hidden','rejected')
                        NOT NULL DEFAULT 'pending_approval',

    -- Module 11 Phase A
    viewing_mode        ENUM('landlord_led','agent_led','either') NOT NULL DEFAULT 'either',
    agent_verified_at   TIMESTAMP NULL DEFAULT NULL,
    agent_verified_by   INT DEFAULT NULL,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    FOREIGN KEY (landlord_id)       REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_city_status (city, status),
    INDEX idx_landlord (landlord_id),
    INDEX idx_verified (agent_verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. PROPERTY_IMAGES — gallery
-- ============================================================================
CREATE TABLE property_images (
    id              INT NOT NULL AUTO_INCREMENT,
    property_id     INT NOT NULL,
    image_path      VARCHAR(255) NOT NULL,
    is_primary      TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_property (property_id, is_primary DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. BOOKINGS — FSM with Module 11 Phase A inspection states
-- ============================================================================
CREATE TABLE bookings (
    id                  INT NOT NULL AUTO_INCREMENT,
    student_id          INT NOT NULL,
    property_id         INT NOT NULL,
    landlord_id         INT NOT NULL,
    agent_id            INT DEFAULT NULL,

    start_date          DATE NOT NULL,
    end_date            DATE NOT NULL,
    duration_type       ENUM('1_semester','2_semesters','1_year','custom') NOT NULL DEFAULT 'custom',

    monthly_rent        DECIMAL(8,2) NOT NULL,
    deposit             DECIMAL(8,2) NOT NULL DEFAULT 0,

    status ENUM(
        'pending_landlord',
        'rejected_by_landlord',
        'pending_agent',
        'agent_assigned',
        'agent_verifying',
        'agent_verified',
        'verification_failed',
        'contract_pending',
        'active',
        'completed',
        'cancelled_by_student',
        'cancelled_by_landlord',
        'cancelled_by_admin'
    ) NOT NULL DEFAULT 'pending_landlord',

    student_note        TEXT DEFAULT NULL,
    landlord_response   TEXT DEFAULT NULL,
    cancellation_reason TEXT DEFAULT NULL,
    cancelled_by        INT DEFAULT NULL,
    rejected_agents     JSON DEFAULT NULL,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    FOREIGN KEY (student_id)   REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (property_id)  REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (landlord_id)  REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (agent_id)     REFERENCES users(id)      ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id)      ON DELETE SET NULL,

    INDEX idx_student_status  (student_id, status),
    INDEX idx_landlord_status (landlord_id, status),
    INDEX idx_agent_status    (agent_id, status),
    INDEX idx_property        (property_id),
    INDEX idx_status          (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. CONTRACTS — signed tenancy
-- ============================================================================
CREATE TABLE contracts (
    id                  INT NOT NULL AUTO_INCREMENT,
    contract_code       VARCHAR(20) NOT NULL UNIQUE,
    booking_id          INT NOT NULL UNIQUE,
    student_id          INT NOT NULL,
    landlord_id         INT NOT NULL,
    agent_id            INT NOT NULL,
    property_id         INT NOT NULL,

    start_date          DATE NOT NULL,
    end_date            DATE NOT NULL,
    monthly_rent        DECIMAL(8,2) NOT NULL,
    deposit             DECIMAL(8,2) NOT NULL,
    terms               TEXT NOT NULL,

    student_signature   VARCHAR(255) DEFAULT NULL,
    student_signed_at   TIMESTAMP NULL DEFAULT NULL,
    student_sign_ip     VARCHAR(45)  DEFAULT NULL,

    landlord_signature  VARCHAR(255) DEFAULT NULL,
    landlord_signed_at  TIMESTAMP NULL DEFAULT NULL,
    landlord_sign_ip    VARCHAR(45)  DEFAULT NULL,

    agent_signature     VARCHAR(255) DEFAULT NULL,
    agent_signed_at     TIMESTAMP NULL DEFAULT NULL,
    agent_sign_ip       VARCHAR(45)  DEFAULT NULL,

    contract_pdf_path   VARCHAR(255) DEFAULT NULL,

    status              ENUM('pending_signatures','active','completed','terminated')
                        NOT NULL DEFAULT 'pending_signatures',
    activated_at        TIMESTAMP NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    FOREIGN KEY (booking_id)  REFERENCES bookings(id)   ON DELETE CASCADE,
    FOREIGN KEY (student_id)  REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (landlord_id) REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (agent_id)    REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,

    INDEX idx_status (status),
    INDEX idx_contract_code (contract_code),
    INDEX idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. AGENT_VERIFICATIONS — pre-contract inspection (Module 11 Phase A)
-- ============================================================================
CREATE TABLE agent_verifications (
    id                       INT NOT NULL AUTO_INCREMENT,
    booking_id               INT NOT NULL UNIQUE,
    agent_id                 INT NOT NULL,
    started_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at             TIMESTAMP NULL DEFAULT NULL,
    deadline_at              TIMESTAMP NULL DEFAULT NULL,

    property_matches_listing TINYINT(1) DEFAULT NULL,
    property_address_correct TINYINT(1) DEFAULT NULL,
    facilities_match         TINYINT(1) DEFAULT NULL,
    landlord_id_matches      TINYINT(1) DEFAULT NULL,
    ownership_doc_sighted    TINYINT(1) DEFAULT NULL,

    inspection_notes         TEXT DEFAULT NULL,
    issues_found             TEXT DEFAULT NULL,
    issue_severity           ENUM('none','minor','major') DEFAULT 'none',

    outcome                  ENUM('in_progress','passed','passed_with_disclosure','failed')
                             NOT NULL DEFAULT 'in_progress',
    student_proceeded_with_disclosure TINYINT(1) DEFAULT NULL,
    student_decision_at      TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_outcome (outcome),
    INDEX idx_deadline (deadline_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. AGENT_VERIFICATION_PHOTOS — inspection evidence
-- ============================================================================
CREATE TABLE agent_verification_photos (
    id              INT NOT NULL AUTO_INCREMENT,
    verification_id INT NOT NULL,
    photo_path      VARCHAR(255) NOT NULL,
    caption         VARCHAR(150) DEFAULT NULL,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (verification_id) REFERENCES agent_verifications(id) ON DELETE CASCADE,
    INDEX idx_verification (verification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. MOVE_IN_INSPECTIONS — handover inventory
-- ============================================================================
CREATE TABLE move_in_inspections (
    id                       INT NOT NULL AUTO_INCREMENT,
    contract_id              INT NOT NULL UNIQUE,
    agent_id                 INT NOT NULL,
    inspected_at             TIMESTAMP NULL DEFAULT NULL,
    inventory_items          JSON DEFAULT NULL,
    overall_notes            TEXT DEFAULT NULL,
    student_acknowledged     TINYINT(1) NOT NULL DEFAULT 0,
    student_acknowledged_at  TIMESTAMP NULL DEFAULT NULL,
    landlord_acknowledged    TINYINT(1) NOT NULL DEFAULT 0,
    landlord_acknowledged_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. MOVE_IN_PHOTOS
-- ============================================================================
CREATE TABLE move_in_photos (
    id              INT NOT NULL AUTO_INCREMENT,
    inspection_id   INT NOT NULL,
    photo_path      VARCHAR(255) NOT NULL,
    caption         VARCHAR(150) DEFAULT NULL,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (inspection_id) REFERENCES move_in_inspections(id) ON DELETE CASCADE,
    INDEX idx_inspection (inspection_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. AGENT_COMMISSIONS — commission tracking
-- ============================================================================
CREATE TABLE agent_commissions (
    id              INT NOT NULL AUTO_INCREMENT,
    contract_id     INT NOT NULL UNIQUE,
    agent_id        INT NOT NULL,

    base_rent       DECIMAL(8,2) NOT NULL,
    commission_pct  DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    commission_amt  DECIMAL(8,2) NOT NULL,
    sst_pct         DECIMAL(5,2) NOT NULL DEFAULT 6.00,
    sst_amt         DECIMAL(8,2) NOT NULL,
    total_payable   DECIMAL(8,2) NOT NULL,

    status          ENUM('pending','earned','released','paid') NOT NULL DEFAULT 'pending',
    earned_at       TIMESTAMP NULL DEFAULT NULL,
    released_at     TIMESTAMP NULL DEFAULT NULL,
    paid_at         TIMESTAMP NULL DEFAULT NULL,
    payment_ref     VARCHAR(100) DEFAULT NULL,

    PRIMARY KEY (id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id)    REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_agent_status (agent_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 14. NOTIFICATIONS
-- ============================================================================
CREATE TABLE notifications (
    id              INT NOT NULL AUTO_INCREMENT,
    user_id         INT NOT NULL,
    type            VARCHAR(50) NOT NULL,
    title           VARCHAR(150) NOT NULL,
    message         TEXT NOT NULL,
    link_url        VARCHAR(255) DEFAULT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 15. FRIEND_REQUESTS — DORMANT (to be replaced by partners table)
-- ============================================================================
CREATE TABLE friend_requests (
    id              INT NOT NULL AUTO_INCREMENT,
    requester_id    INT NOT NULL,
    receiver_id     INT NOT NULL,
    status          ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
    message         VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id)  REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_pair_pending (requester_id, receiver_id, status),
    INDEX idx_receiver_status (receiver_id, status),
    INDEX idx_requester_status (requester_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 16. FRIENDS — DORMANT
-- ============================================================================
CREATE TABLE friends (
    id                  INT NOT NULL AUTO_INCREMENT,
    user_a              INT NOT NULL,
    user_b              INT NOT NULL,
    became_friends_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_a) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_b) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_pair (user_a, user_b),
    INDEX idx_user_a (user_a),
    INDEX idx_user_b (user_b)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 17. CONVERSATIONS
-- ============================================================================
CREATE TABLE conversations (
    id                      INT NOT NULL AUTO_INCREMENT,
    user_a                  INT NOT NULL,
    user_b                  INT NOT NULL,
    property_id             INT DEFAULT NULL,
    booking_id              INT DEFAULT NULL,
    context_type            ENUM('property_inquiry','booking','friend','agent_case','other')
                            NOT NULL DEFAULT 'other',
    last_message_at         TIMESTAMP NULL DEFAULT NULL,
    last_message_preview    VARCHAR(120) DEFAULT NULL,
    last_sender_id          INT DEFAULT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_a)      REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (user_b)      REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id)  REFERENCES bookings(id)   ON DELETE SET NULL,
    UNIQUE KEY uniq_pair_context (user_a, user_b, property_id, booking_id),
    INDEX idx_user_a (user_a, last_message_at DESC),
    INDEX idx_user_b (user_b, last_message_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 18. MESSAGES
-- ============================================================================
CREATE TABLE messages (
    id                  INT NOT NULL AUTO_INCREMENT,
    conversation_id     INT NOT NULL,
    sender_id           INT NOT NULL,
    body                TEXT NOT NULL,
    sent_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at             TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)       REFERENCES users(id)         ON DELETE CASCADE,
    INDEX idx_convo_time (conversation_id, sent_at),
    INDEX idx_unread (conversation_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BOOTSTRAP ADMIN ACCOUNT
-- ============================================================================
-- Email:    admin@rentbridge.local
-- Password: ChangeMe123!
-- CHANGE THIS IMMEDIATELY after first login.

INSERT INTO users (email, password_hash, primary_role, status) VALUES (
    'admin@rentbridge.local',
    '$2y$10$wH8E1lF4xQc2bU3pY5G7vOzj0XQk6Z.M9rWqJ.HhJqK4Yt5L6tBJa',
    'admin',
    'active'
);

-- ============================================================================
-- DONE — Verify with:
--   SHOW TABLES;                                    (should list 18)
--   SELECT * FROM users;                            (should show 1 admin)
-- ============================================================================

-- ADD a single boolean opt-in column instead
ALTER TABLE landlords
    ADD COLUMN allow_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER phone;
ALTER TABLE agents
    ADD COLUMN allow_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER phone;

    ALTER TABLE conversations
    ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER last_sender_id,
    ADD COLUMN locked_reason VARCHAR(255) DEFAULT NULL AFTER is_locked;

--===========================================================================
-- 13.6.26 

CREATE TABLE IF NOT EXISTS property_documents (
    id              INT NOT NULL AUTO_INCREMENT,
    property_id     INT NOT NULL,
    document_type   ENUM('ownership_proof','utility_bill','other') NOT NULL DEFAULT 'other',
    file_path       VARCHAR(255) NOT NULL,
    original_name   VARCHAR(150) DEFAULT NULL,
    file_size       INT NOT NULL DEFAULT 0,
    mime_type       VARCHAR(80) DEFAULT NULL,
    uploaded_by     INT NOT NULL,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes           VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)      ON DELETE CASCADE,
    INDEX idx_property (property_id),
    INDEX idx_type     (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS co_tenancy_posts (
    id              INT NOT NULL AUTO_INCREMENT,
    poster_id       INT NOT NULL,
    property_id     INT NOT NULL,
    title           VARCHAR(150) DEFAULT NULL COMMENT 'optional headline, defaults to property title',
    message         TEXT NOT NULL COMMENT 'why they want housemates, lifestyle preferences',
    housemates_needed INT NOT NULL DEFAULT 1 COMMENT 'how many more co-tenants wanted',
    status          ENUM('open','filled','cancelled','expired') NOT NULL DEFAULT 'open',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (poster_id)   REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_poster (poster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;