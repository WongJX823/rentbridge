# RentBridge — TODO

## Option A: Academic-calendar–driven tenancy durations

**Goal:** Make tenancy/contract durations match the real UTeM academic calendar instead of approximate month/week arithmetic.

**Context / why:** As of the B+C interim fix (see below), "1 semester" = +18 weeks and "2 semesters" = +39 weeks (~9 months, incl. semester break), counted from the student's chosen move-in date. This is far more accurate than the old "2 sem = 8 months", but it still floats from a fixed move-in date and does not land on the actual UTeM semester start/end dates.

**What to build:**
1. Add an `academic_terms` reference table, e.g.
   `academic_terms(id, session VARCHAR, term ENUM('sem1','sem2','short'), start_date DATE, end_date DATE)`,
   seeded once per session from the official UTeM calendar
   (https://www.utem.edu.my/en/academic-calendar.html).
2. In the booking flow (`tenancies/new.php`) and the agent term form
   (`chat/conversation.php` term picker + `chat/send_tenant_form.php` +
   `student/tenant_form.php`), let the user pick a **term** (e.g. "Semester 1
   2025/2026") rather than a raw month count. Resolve `start_date` / `end_date`
   from `academic_terms`:
   - 1 semester  -> chosen term's start_date .. end_date
   - 2 semesters -> sem1.start_date .. sem2.end_date (spans the inter-semester
     break as continuous occupancy)
3. Keep `tenancies.duration_type` and the contract month count derived from the
   real dates (`includes/contracts.php` already computes months from
   start/end), so the contract stays accurate automatically.
4. Show the breakdown in the contract: "Semester 1 (dates) + semester break
   (dates) + Semester 2 (dates)".
5. Decide & store the break policy (continuous occupancy vs vacate) — currently
   the contract states continuous occupancy (terms clause 9).

**Files involved:**
- `tenancies/new.php` — duration switch + option cards
- `chat/conversation.php` (~line 892) — agent term picker
- `chat/send_tenant_form.php`, `student/tenant_form.php` — term_months handling
- `includes/contracts.php` — term label + contract render
- DB migration: new `academic_terms` table

**Note:** Also fix the latent `tenancies.duration_type` enum mismatch — the form
stores keys like `semester_4`/`academic_8`/`full_year_12`, but the column enum
is `('1_semester','2_semesters','1_year','custom')`. Map form key -> enum value
on insert.

---
New
Property-Pending
   May do a status progress bar to show status 1 pending -> 2 wait for inspection -> inspection complete -> 4 available now

### Done (interim) — Option B + C
- `tenancies/new.php`: 1 sem = +18 weeks, 2 sem = +39 weeks (~9 months); cards relabelled.
- `chat/conversation.php`: agent term picker "2 semesters" changed 8 -> 9 months, incl. semester break.
- `student/tenant_form.php`: added 9-month term label.
- `includes/contracts.php`: added terms clause 9 (continuous period incl. semester break) + "incl. any semester break" note under Duration.
