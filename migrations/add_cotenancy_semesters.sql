-- Add semesters_needed to co_tenancy_posts
ALTER TABLE `co_tenancy_posts`
    ADD COLUMN `semesters_needed` TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'How many semesters the poster intends to rent (1–6)'
        AFTER `housemates_needed`;
