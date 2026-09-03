-- Race preference for property listings and housemate posts, plus a student
-- race identity used to skip non-matching posts/listings when browsing.
--   properties.race_preference       -> who the landlord will rent the place to
--   co_tenancy_posts.race_preference -> who the poster wants as housemates
--   students.race                    -> the student's own race (NULL = not set)
-- 'any' / NULL keep existing rows unconstrained.

ALTER TABLE `properties`
    ADD COLUMN `race_preference` ENUM('any','malay','chinese','indian','others') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred tenant race for this listing'
        AFTER `gender_preference`;

ALTER TABLE `co_tenancy_posts`
    ADD COLUMN `race_preference` ENUM('any','malay','chinese','indian','others') NOT NULL DEFAULT 'any'
        COMMENT 'Preferred housemate race'
        AFTER `gender_preference`;

ALTER TABLE `students`
    ADD COLUMN `race` ENUM('malay','chinese','indian','others') DEFAULT NULL
        COMMENT 'Student race for housemate race-matching'
        AFTER `gender`;
