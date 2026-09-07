-- Change race_preference from a single-value ENUM to a multi-value SET, so a
-- landlord/poster can restrict to more than one race at once (e.g. "Malay or
-- Chinese only") instead of exactly one.
--   properties.race_preference       -> who the landlord will rent the place to
--   co_tenancy_posts.race_preference -> who the poster wants as housemates
-- Existing 'any' rows become '' (empty set = no restriction, matches
-- everyone) - handled by rb_race_norm()/rb_race_matches() in includes/race.php.
-- students.race (a student's own single identity) is unchanged.

ALTER TABLE `properties`
    ADD COLUMN `race_preference_new` SET('malay','chinese','indian','others') NOT NULL DEFAULT ''
        COMMENT 'Preferred tenant race(s) for this listing — empty = any'
        AFTER `race_preference`;

UPDATE `properties`
   SET `race_preference_new` = IF(`race_preference` = 'any', '', `race_preference`);

ALTER TABLE `properties`
    DROP COLUMN `race_preference`,
    CHANGE COLUMN `race_preference_new` `race_preference`
        SET('malay','chinese','indian','others') NOT NULL DEFAULT ''
        COMMENT 'Preferred tenant race(s) for this listing — empty = any';

ALTER TABLE `co_tenancy_posts`
    ADD COLUMN `race_preference_new` SET('malay','chinese','indian','others') NOT NULL DEFAULT ''
        COMMENT 'Preferred housemate race(s) — empty = any'
        AFTER `race_preference`;

UPDATE `co_tenancy_posts`
   SET `race_preference_new` = IF(`race_preference` = 'any', '', `race_preference`);

ALTER TABLE `co_tenancy_posts`
    DROP COLUMN `race_preference`,
    CHANGE COLUMN `race_preference_new` `race_preference`
        SET('malay','chinese','indian','others') NOT NULL DEFAULT ''
        COMMENT 'Preferred housemate race(s) — empty = any';
