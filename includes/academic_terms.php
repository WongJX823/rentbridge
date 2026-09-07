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

/**
 * Standard tenancy term lengths (agent "Set tenancy terms" dropdown) -> how many
 * consecutive real academic_terms rows that length is meant to cover. "2 years"
 * / "3 years" mean 2 / 3 full academic sessions (sem1 + sem2 + short = 3 terms
 * each), not a generic 24/36-calendar-month span.
 */
function term_months_to_semester_count(int $termMonths): ?int {
    return match ($termMonths) {
        13 => 3,
        18 => 4,
        24 => 6,
        36 => 9,
        default => null,
    };
}

/**
 * duration_type enum value for a given term length, matching the labels
 * includes/contracts.php and agent/generate_contract.php actually render.
 */
function term_months_to_duration_type(int $termMonths): string {
    return match ($termMonths) {
        13 => 'three_semesters',
        18 => 'four_semesters',
        24 => 'two_years',
        36 => 'three_years',
        default => match (true) {
            $termMonths <= 5   => '1_semester',
            $termMonths <= 10  => '2_semesters',
            $termMonths === 12 => '1_year',
            default            => 'custom',
        },
    };
}

/**
 * Resolve a tenancy/contract end date the same way everywhere: chain real
 * academic_terms rows instead of naive "start date + N months" arithmetic,
 * whenever $termMonths is one of the standard semester-aligned lengths and
 * the calendar has enough future terms published. Falls back to calendar-
 * month arithmetic for non-standard lengths (e.g. a landlord's free-typed
 * custom term) or when the calendar isn't populated far enough ahead yet.
 *
 * @return array{end_date:string, resolved_from_calendar:bool}
 */
function resolve_term_end_date(string $startDate, int $termMonths): array {
    $semesterCount = term_months_to_semester_count($termMonths);
    if ($semesterCount !== null) {
        $stmt = db()->prepare("
            SELECT end_date FROM academic_terms
             WHERE end_date >= ?
             ORDER BY start_date ASC
        ");
        $stmt->execute([$startDate]);
        $endDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($endDates) >= $semesterCount) {
            return ['end_date' => $endDates[$semesterCount - 1], 'resolved_from_calendar' => true];
        }
    }

    $end = (new DateTime($startDate))->modify("+{$termMonths} months");
    return ['end_date' => $end->format('Y-m-d'), 'resolved_from_calendar' => false];
}
