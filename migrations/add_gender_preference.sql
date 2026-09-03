-- Gender preference for property listings and housemate posts.
--   properties.gender_preference       -> who the landlord will rent the place to
--   co_tenancy_posts.gender_preference -> who the poster wants as housemates
-- 'any' keeps existing rows unconstrained.

ALTER TABLE `properties`
    ADD COLUMN `gender_preference` ENUM('any','male','female') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred tenant gender for this listing'
        AFTER `viewing_mode`;

ALTER TABLE `co_tenancy_posts`
    ADD COLUMN `gender_preference` ENUM('any','male','female') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred housemate gender'
        AFTER `semesters_needed`;
