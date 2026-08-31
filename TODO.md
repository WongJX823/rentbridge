# RentBridge — TODO

## Schedule snapshot  (visual: schedule_gantt.png)

Dev -> Deploy -> Test, by phase — P1 Core dev · P2 Data integrity · P3 Testing ·
P4 Security · P5 Deploy prep · P6 Deploy · P7 UAT.

- **Done:** property status bar; property map pinpoint; academic-calendar
  importer (GPT-4o); PHPUnit backend suite + single test runner.
- **In progress:** data integrity (migrations written, not yet applied);
  security review.
- **Pending:** durations wiring + duration_type enum fix; multi-role app wiring;
  apply migrations; full green test run; deploy prep; deploy; UAT.
- **Report:** Ch2 / §3.3.1 / §4.3.2 done; Ch6 + Ch7 merged into Report_v3.docx;
  README done; Ch5 (Implementation) not written.

---


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
Property-Pending  [DONE]
   Status progress bar (Pending -> Awaiting inspection -> Inspection complete -> Available now,
   plus a Rejected terminal). Reusable component includes/property_status_bar.php; shown on the
   landlord + admin property detail pages and a compact variant on the landlord + admin property lists.

Property map pinpoint (Leaflet + OpenStreetMap)  [IN PROGRESS]
   Done: reusable includes/map.php (Leaflet, no API key) with rb_map_picker()
   (interactive click/drag pin + Nominatim "find my address"; saves latitude/
   longitude) wired into landlord/add_property.php, and rb_map_view() (read-only
   map + "Get directions") on the public property.php detail page. latitude/
   longitude now saved on property create + edit.
   Map view now also on landlord/property.php + admin/property.php (public
   property.php already had it; student/property.php is only a partial included
   by property.php, so it is covered).
   To do: test live (needs Apache + internet for tiles/geocoding).
   -- original note --
   Show each property on a map with a location pin, and let the landlord
   drop/adjust the pin when adding a property.
   - `properties` already has `latitude`, `longitude`, `maps_url` columns to use.
   - Landlord add/edit (`landlord/add_property.php`): embed a Google Maps
     (or Leaflet — see `feat/property-map-leaflet` branch) picker; save the
     chosen lat/long.
   - Property detail (`property.php` / `student/property.php`): render the map
     with the pin + a "Get directions" link (`maps_url`).
   - Decide provider: Google Maps JS API (needs an API key) vs Leaflet +
     OpenStreetMap (no key). Note: strict CSP if embedding in artifacts.

## Multi-role support (overlapping roles)

**Goal:** Let one user hold more than one role at the same time (e.g. a student
who is also a landlord). Today the model is **disjoint + partial**: authorization
reads a single `users.primary_role`, so a user is effectively one role only, and
`admin` has no subtype table.

**Status:** DB migration added — `migrations/add_user_roles.sql` creates a
`user_roles(user_id, role, is_primary)` junction (the explicit overlapping M:M)
and backfills it from `primary_role` and from existing `students` / `landlords`
/ `agents` profile rows. **Not yet wired into the app.**

**Still to do (application layer):**
1. Change authorization to "does the user have role X?" — update `require_role()`
   / login / dashboards to check `user_roles` (EXISTS/join) instead of comparing
   `users.primary_role` directly.
2. Replace hard-coded `WHERE primary_role = 'agent' / 'admin'` queries (e.g.
   `includes/agent_assignment.php`, `includes/tenancies.php`,
   `includes/transfers.php`, `includes/reports.php`) with `user_roles` lookups.
3. Add a **role switcher** in the UI and activate the dormant
   `users.last_used_role` column to remember the active context.
4. Add "become a landlord / register another role" flows that insert into
   `user_roles` + create the matching subtype profile row.
5. Decide completeness: give `admin` a proper subtype/flag so the model can be
   made **total** if desired.

---

## Data protection: audit log, soft-delete & backups

**Why:** Many FKs use `ON DELETE CASCADE`, so deleting one `users` row silently
wipes that user's `tenancies`, `contracts`, `agent_commissions`, `messages`, etc.
Those are legal/financial records that must be traceable and recoverable. No
separate database is needed — keep everything in `dbrb_2026`.

**Status:** Audit log added — `migrations/add_audit_log.sql` creates an
`audit_log` table and AFTER insert/update/delete triggers on `contracts`,
`tenancies`, and `agent_commissions` (captures old/new JSON snapshots + actor).

**Still to do:**
1. [DONE] **Set the actor** — includes/auth.php now runs
   `SET @app_user_id = <id>` once per request for logged-in users, so audit rows
   record who made each change.
2. **Soft-delete the legal/financial tables.** Add `deleted_at TIMESTAMP NULL`
   (contracts/tenancies already have `cancelled_*` / `terminated` statuses) and
   filter it out in queries instead of running `DELETE`. Change
   `ON DELETE CASCADE` to `RESTRICT` / `SET NULL` on `contracts`, `tenancies`,
   and `agent_commissions` so a user deletion can never destroy them.
3. **Extend audit coverage** to more tables (users, properties) and add more
   columns to the `JSON_OBJECT(...)` snapshots as needed.
4. **Backups** — schedule regular dumps as the real recover-lost-data safety net:
   `mysqldump -u root dbrb_2026 > backups/dbrb_2026_$(date +%F).sql`
   and consider enabling the MySQL binary log for point-in-time recovery.

---

## Contracts

**Merge hard-sign (wet) and e-sign into one final contract.**
Today a contract can have a MIX of signatures: co-tenants WITH an account
e-sign digitally (signature image embedded in the PDF), while co-tenants WITHOUT
an account wet-sign the printed copy. Those wet signatures never make it back
into the digital document, so the "signed" PDF is incomplete for mixed groups.
- Goal: one final signed document that carries BOTH the embedded e-signatures
  and the physical/wet signatures.
- Options: (a) let the agent upload a scan/photo of each wet signature so it is
  embedded on that signer's line like an e-signature; or (b) let the agent
  upload the scanned wet-signed pages and append/merge them with the e-signed
  PDF. Option (a) keeps a single clean PDF.
- Touches: includes/contracts.php (rb_agreement_html signature blocks +
  generate_contract_pdf), co_tenants.signature_data, contracts/view.php,
  agent/upload_signed_contract.php.

**Contract template dedup (single source of truth).**
`agent/generate_contract.php` still holds its OWN inline copy of the formal
tenancy-agreement HTML; the signed download already uses the shared
`rb_agreement_html()` in includes/contracts.php. Refactor the agent page to call
`rb_agreement_html()` so the blank and signed contracts can never drift apart.
Low risk: map the page's variables into the function's data array; keep output
identical. (This drift is what caused the earlier "two different-looking
contracts" issue.)

---

### Done (interim) — Option B + C
- `tenancies/new.php`: 1 sem = +18 weeks, 2 sem = +39 weeks (~9 months); cards relabelled.
- `chat/conversation.php`: agent term picker "2 semesters" changed 8 -> 9 months, incl. semester break.
- `student/tenant_form.php`: added 9-month term label.
- `includes/contracts.php`: added terms clause 9 (continuous period incl. semester break) + "incl. any semester break" note under Duration.
