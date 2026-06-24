-- Housemate application system + group chat support
-- Run against dbrb_2026

-- Applications table
CREATE TABLE IF NOT EXISTS co_tenancy_applications (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    post_id        INT NOT NULL,
    applicant_id   INT NOT NULL,
    message        TEXT,
    status         ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at   DATETIME NULL,
    UNIQUE KEY uniq_post_applicant (post_id, applicant_id),
    FOREIGN KEY (post_id)      REFERENCES co_tenancy_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Group conversation participants (for housemate group chats)
CREATE TABLE IF NOT EXISTS conversation_participants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id         INT NOT NULL,
    joined_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_conv_user (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add group conversation link to posts
ALTER TABLE co_tenancy_posts
    ADD COLUMN IF NOT EXISTS group_conversation_id INT NULL;

-- Allow group conversations to have no user_b (group mode)
ALTER TABLE conversations MODIFY user_b INT NULL;

-- Add housemate_group to context_type enum
ALTER TABLE conversations
    MODIFY context_type ENUM(
        'property_inquiry','tenancy','friend','agent_case',
        'other','contract_prep','housemate_group'
    ) NOT NULL DEFAULT 'other';
