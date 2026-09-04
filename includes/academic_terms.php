<?php
require_once __DIR__ . '/auth.php';

/**
 * Single bookable terms (for a "1 semester" tenancy) — any term whose start
 * date hasn't passed yet, earliest first.
 */
function get_upcoming_single_terms(): array {
    $stmt = db()->prepare("
        SELECT id, session, term, label, start_date, end_date
          FROM academic_terms
         WHERE start_date >= CURDATE()
         ORDER BY start_date ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Sessions with both a Semester 1 and Semester 2 row (for a "2 semesters /
 * full academic year" tenancy spanning the inter-semester break as
 * continuous occupancy) — sem1 hasn't started yet, earliest first.
 * Each row: session, sem1_id, sem1_label, sem1_start, sem2_id, sem2_label, sem2_end.
 */
function get_upcoming_academic_years(): array {
    $stmt = db()->prepare("
        SELECT s1.session,
               s1.id AS sem1_id, s1.label AS sem1_label, s1.start_date AS sem1_start,
               s2.id AS sem2_id, s2.label AS sem2_label, s2.end_date   AS sem2_end
          FROM academic_terms s1
          JOIN academic_terms s2 ON s2.session = s1.session AND s2.term = 'sem2'
         WHERE s1.term = 'sem1'
           AND s1.start_date >= CURDATE()
         ORDER BY s1.start_date ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/** One term row by id, or null. */
function get_academic_term(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM academic_terms WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}
