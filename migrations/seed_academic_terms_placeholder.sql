-- Placeholder UTeM academic-calendar dates so tenancies/new.php has real
-- terms to offer immediately. academic_terms was created empty by
-- add_academic_terms.sql — nothing had populated it yet.
--
-- These dates are realistic UTeM-pattern estimates (Sem 1 mid-Sept, Sem 2
-- mid-Feb, short sem late June), NOT sourced from an official calendar PDF.
-- Superseded automatically the moment an admin imports the real calendar via
-- admin/academic_calendar.php (INSERT IGNORE — re-running this after a real
-- import is a no-op for any session/term pair that already exists).
-- Run against dbrb_2026. Safe to re-run.

INSERT IGNORE INTO academic_terms (session, term, label, start_date, end_date) VALUES
  ('2026/2027', 'sem1',  'Semester 1', '2026-09-14', '2027-01-18'),
  ('2026/2027', 'sem2',  'Semester 2', '2027-02-15', '2027-06-21'),
  ('2026/2027', 'short', 'Short Semester', '2027-06-28', '2027-08-16'),
  ('2027/2028', 'sem1',  'Semester 1', '2027-09-13', '2028-01-17'),
  ('2027/2028', 'sem2',  'Semester 2', '2028-02-14', '2028-06-19');
