-- Student gender, used to skip non-matching housemate posts when browsing.
-- NULL = not specified (student hasn't set it yet); no gender filtering applies.

ALTER TABLE `students`
    ADD COLUMN `gender` ENUM('male','female') DEFAULT NULL
        COMMENT 'Student gender for housemate gender-matching'
        AFTER `phone`;
