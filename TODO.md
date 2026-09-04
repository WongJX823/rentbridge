# RentBridge — TODO

## Schedule snapshot  (visual: schedule_gantt.png)

Dev -> Deploy -> Test, by phase — P1 Core dev · P2 Data integrity · P3 Testing ·
P4 Security · P5 Deploy prep · P6 Deploy · P7 UAT.

- **Done:** property status bar; property map pinpoint; academic-calendar
  importer (GPT-4o); PHPUnit backend suite + single test runner; migrations
  applied to dev DB; Playwright E2E suite repaired and passing (36/39, 3
  justified skips for real product gaps — see "Found while fixing E2E" below).
- **In progress:** data integrity (soft-delete + audit coverage still open);
  security review.
- **Pending:** durations wiring + duration_type enum fix; multi-role app wiring;
  deploy prep; deploy; UAT.
- **Report:** Ch2 / §3.3.1 / §4.3.2 done; Ch6 + Ch7 merged into Report_v3.docx;
  README done; Ch5 (Implementation) not written.

### Found while fixing E2E (two real gaps, not test bugs) — both DONE

- **`/verify.php` doesn't exist.** [DONE] Built as a public, no-login page —
  takes `?ref=<contract_code>`, shows non-sensitive details (property, city/
  type, tenancy period, monthly rent, tenant name(s), status, issue date),
  never IC/phone/email/signatures. `contracts/view.php`'s footer note now
  links to the real URL instead of the fictitious `rentbridge.com/verify/<code>`
  path. `tests/flow3-3tenants-wetsign.spec.js` UC-11 un-skipped.
- **No UI to add a co-tenant to an existing tenancy.** [DONE] `agent/case.php`
  now has an "Add a late co-tenant" form (name/IC/phone/email) in the
  co-tenants panel, posting to new `agent/add_cotenant.php`. Gated server-side
  to tenancy status `agent_verifying`/`agent_verified`/`contract_pending` only
  (blocked once the contract is active/closed, since that needs a formal
  amendment, not this). Calls `ensure_cotenant_sign_tokens()` so an
  account-less addition immediately gets a sign token, and notifies the
  primary tenant + landlord. Not added to `admin/tenancy.php` — admin's page
  is oversight/cancellation only, and the agent already owns the co-tenant
  lifecycle (sends the tenant-info form, generates the contract), so it's the
  natural single owner for this action too. Also added flash-message
  rendering to `agent/case.php` (it had none — redirects from other agent
  endpoints like `generate_contract.php` were setting flashes that were never
  displayed).

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

Gender preference (property listing + find-housemate)  [DONE, migration pending apply]
   Landlords can set a preferred tenant gender on a listing, and students can set
   a preferred housemate gender on a co-tenancy post. Any / Male only / Female only.
   - migrations/add_gender_preference.sql: adds gender_preference ENUM('any',
     'male','female') DEFAULT 'any' to `properties` and `co_tenancy_posts`.
   - includes/gender.php: shared rb_gender_norm/options/label/badge helpers.
   - Property: select added to landlord/add_property.php (create+edit) and
     auth/register_landlord_step2.php; saved on INSERT/UPDATE; badge shown on
     property.php header (only when restricted).
   - Housemate: select added to student/find_housemates.php; saved on INSERT;
     badge shown on housemate_post.php, student/partners.php cards, manage_post.php.
   - Display uses `?? 'any'` so it degrades safely before the migration is applied;
     the INSERT/UPDATE need the column, so APPLY THE MIGRATION before creating/
     editing listings or posts.
   Gender-match browse filter (housemate posts)  [DONE, migration pending apply]
   - migrations/add_student_gender.sql: adds students.gender ENUM('male','female')
     NULL (NULL = not specified). Collected optionally at student registration
     (auth/register_student.php) and editable in student/profile.php.
   - student/partners.php browse tab: opt-in "Matching my gender" checkbox (shown
     only once the student has set a gender; otherwise a "Set gender to filter"
     link to the profile). When ticked, includes/partners.php list_co_tenancy_posts
     adds WHERE (gender_preference='any' OR gender_preference = my gender), so
     posts the student isn't eligible for are skipped. Double-guarded: filter only
     applies when the viewer's gender is set. Default is show-all (opt-in).
   Gender-match filter also applied to PROPERTY listings browse (listings.php):
   opt-in "Only show listings matching my gender" checkbox (students only; hint to
   set gender in profile otherwise) -> adds WHERE (p.gender_preference='any' OR
   p.gender_preference = my gender). Listing cards also show a gender badge when
   restricted. Property gender_preference is editable via landlord/add_property.php
   edit mode (loads SELECT * so the saved value pre-selects); student gender is
   editable via student/profile.php.
   Race preference (mirrors gender end-to-end)  [DONE, migration pending apply]
   - migrations/add_race_preference.sql: adds properties.race_preference &
     co_tenancy_posts.race_preference ENUM('any','malay','chinese','indian',
     'others') DEFAULT 'any', and students.race ENUM('malay','chinese','indian',
     'others') NULL.
   - includes/race.php: rb_race_norm/values/options/label/badge +
     rb_race_identity_* helpers.
   - Landlord property (add_property.php create+edit, register_landlord_step2.php):
     "Preferred tenant race" select saved on INSERT/UPDATE; badge on property.php
     + listings.php cards.
   - Housemate post (find_housemates.php): "Preferred housemate race" select saved
     on INSERT; badge on housemate_post.php, partners.php cards, manage_post.php.
   - Student race collected at registration (register_student.php) + editable in
     student/profile.php.
   - Race-match opt-in filter on BOTH browse tabs (student/partners.php posts and
     listings.php properties): "Matching my race" checkbox -> WHERE
     (race_preference='any' OR race_preference = my race). Double-guarded on the
     viewer's race being set; independent of the gender-match toggle.
   Housemate posts remain NON-editable after creation (by design) — gender/race
   are set once at creation.
   Compatibility score now gates on gender/race (includes/partners.php):
   post_matches_identity() decides eligibility; compatibility_score() caps an
   ineligible viewer's score at 15 (so mismatches sort to the bottom and never
   read as a good match), and the card shows a "Not eligible — limited to a
   different gender/race" badge instead of High/Medium/Low. Unknown viewer
   identity or an 'any' post = not gated. Soft factors (city/budget/university/
   move-in) unchanged.

New
Property-Pending  [DONE]
   Status progress bar (Pending -> Awaiting inspection -> Inspection complete -> Available now,
   plus a Rejected terminal). Reusable component includes/property_status_bar.php; shown on the
   landlord + admin property detail pages and a compact variant on the landlord + admin property lists.

Property map pinpoint (Google Maps)  [IN PROGRESS]
   Done: reusable includes/map.php with rb_map_view() = KEYLESS Google Maps embed
   iframe + "Get directions" (no API key needed) on property.php, landlord/
   property.php, admin/property.php.
   Pin-first drawer redesign (from the "Landlord Map Pinpoint" Claude Design
   wireframe) is DONE: the map is now COLLAPSED by default behind a pin icon
   tucked into the street-address field. Tapping it opens a right-side "Pin your
   property" drawer (search prefilled from the typed address + geocode results,
   "My location" GPS, click-to-drop, drag-to-fine-tune, Cancel/Done). After
   pinning, a green "Location pinned + coords / Edit pin" row appears and the pin
   icon turns filled-green. The Google Maps link is demoted to a collapsed
   optional toggle. Pinning stays optional — submit never blocks on it. Edge
   states handled: GPS denied, address not found, mobile full-height sheet.
   Implemented as rb_address_pin_field() + rb_maps_link_field() +
   rb_map_pinpoint_assets() (drawer + JS, Google Maps JS loaded lazily on first
   open). Wired into BOTH landlord/add_property.php (create/edit) AND
   auth/register_landlord_step2.php (sign-up) — the latter now also persists
   latitude/longitude/maps_url on the properties INSERT. No key -> degrades to
   manual latitude/longitude inputs so the form still works. Legacy inline
   rb_map_picker() kept for any other callers.
   Pin <-> Google Maps link two-way sync (last edit wins): confirming a pin also
   reverse-geocodes to fill the street address / city / postcode AND rewrites the
   optional Google Maps link to a canonical ?q=lat,lng URL; conversely pasting a
   Maps link resolves its coordinates (landlord/geocode_link.php, follows
   goo.gl short links) and overwrites the pin + address form. So the two can
   never contradict. City is only set when it matches an allowed Melaka area.
   To do: add a real API key + restrict it to the domain; test live.
   (config/google.php now holds a working key locally — restrict it before deploy.)
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
2. [DONE] **Soft-delete the legal/financial tables** —
   `migrations/add_soft_delete_and_restrict_cascade.sql` adds `deleted_at
   TIMESTAMP NULL` to `contracts`, `tenancies`, and `agent_commissions`, and
   changes every `ON DELETE CASCADE` on those tables (from `users` and
   `properties`) to `RESTRICT`. Verified live: deleting a landlord or student
   with an active tenancy now fails with an FK error instead of silently
   wiping the tenancy/contract/commission chain. `tenancies.agent_id` and
   `.cancelled_by` were left on `SET NULL` on purpose — losing that reference
   doesn't destroy the tenancy record itself.
   Nothing in the app issues a hard `DELETE` on these three tables today, so
   `deleted_at` is laid down for future use, not yet read by any query — add
   `WHERE deleted_at IS NULL` wherever a delete-this-record UI gets built.
   One adjacent gap surfaced but left alone (out of scope for this item):
   `properties.landlord_id` -> `users` is still `CASCADE`, so deleting a
   landlord still deletes their properties outright (RESTRICT now stops it
   one hop later, at tenancies/contracts).
3. **Extend audit coverage** to more tables (users, properties) and add more
   columns to the `JSON_OBJECT(...)` snapshots as needed.
4. **Backups** — schedule regular dumps as the real recover-lost-data safety net:
   `mysqldump -u root dbrb_2026 > backups/dbrb_2026_$(date +%F).sql`
   and consider enabling the MySQL binary log for point-in-time recovery.

---

## Contracts

**Everyone signs in-system, merged into one contract.**  [DONE]
Previously co-tenants WITHOUT an account had to wet-sign the printed copy, so
their signatures never reached the digital PDF and a mixed-group contract could
never fully e-sign. Now every party signs in-system and all signatures merge
into one PDF:
- migrations/add_cotenant_sign_token.sql: adds co_tenants.sign_token.
- includes/contracts.php: ensure_cotenant_sign_tokens() (tokens for account-less
  co-tenants, generated at contract creation + backfilled), cotenant_sign_url(),
  apply_signature_by_token() (link-based signing, same signing order + all-signed
  activation as apply_signature()).
- contracts/sign_link.php: public token-signed signature-pad page (no login).
- contracts/view.php: shows a shareable signing link per account-less co-tenant.
- generate_contract_pdf() already embeds every signature, so the final PDF is one
  merged contract. Degrades gracefully if the migration is not yet applied.
- Agent-triggered email send: DONE. includes/contracts.php
  send_cotenant_sign_links() emails each account-less co-tenant their signing
  link (PHPMailer); contracts/view.php has an agent-only "Send signing links"
  button (the agent controls when it goes out), plus the copy-link fallback.
- To do: WhatsApp delivery; optional token expiry; auto-resend reminders.

**Contract template dedup (single source of truth).**  [DONE]
`agent/generate_contract.php` now builds a data array and calls the shared
`rb_agreement_html()` in includes/contracts.php (same function used by the signed
download), so the blank and signed contracts share one template and can no longer
drift apart. The inline template / buildSignatureBlock have been removed.

---

### Done (interim) — Option B + C
- `tenancies/new.php`: 1 sem = +18 weeks, 2 sem = +39 weeks (~9 months); cards relabelled.
- `chat/conversation.php`: agent term picker "2 semesters" changed 8 -> 9 months, incl. semester break.
- `student/tenant_form.php`: added 9-month term label.
- `includes/contracts.php`: added terms clause 9 (continuous period incl. semester break) + "incl. any semester break" note under Duration.
