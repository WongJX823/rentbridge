# RentBridge — TODO

## Schedule snapshot  (visual: schedule_gantt.png)

Dev -> Deploy -> Test, by phase — P1 Core dev · P2 Data integrity · P3 Testing ·
P4 Security · P5 Deploy prep · P6 Deploy · P7 UAT.

- **Done:** property status bar; property map pinpoint; academic-calendar
  importer (GPT-4o); PHPUnit backend suite + single test runner; migrations
  applied to dev DB; Playwright E2E suite repaired and passing (39/39, 1
  pre-existing conditional skip — see "Deploy prep" below); `/verify.php`
  public verify page; late co-tenant UI; audit coverage extended to
  `users`/`properties`; DB backup script; security review (1 finding, fixed
  — see below); multi-role app wiring; academic-calendar durations for the
  direct booking flow + the `duration_type` enum data-corruption bug it
  uncovered; deploy prep (config hardening, migration audit, deploy runbook —
  see "Deploy prep" below).
- **In progress:** none currently open.
- **Pending:** deploy; UAT. (Academic-calendar durations for the
  agent-mediated flow intentionally deferred — see Option A section.)
- **Report:** Ch2 / §3.3.1 / §4.3.2 done; Ch6 + Ch7 merged into Report_v3.docx;
  README done; Ch5 (Implementation) not written.

### Deploy prep — DONE (target: shared/VPS hosting)

- **Leaked SMTP credentials.** `includes/mail_config.php` had live Mailtrap
  SMTP username/password hardcoded and committed — the repo is public on
  GitHub, so these were exposed since commit `864f914`. Refactored to the
  same env-var + gitignored-local-fallback pattern as `config/google.php`/
  `config/openai.php` (`mailer.php` now degrades safely if the file is
  absent). **Still needs a human step:** rotate the Mailtrap credentials in
  their dashboard — not done as part of this pass.
- **Config hardening.** `config/database.php` no longer leaks the raw PDO
  exception message on a DB-connect failure (generic message unless
  `RB_DEBUG=1`); `contracts/sign.php` no longer force-enables
  `display_errors` regardless of production `php.ini`.
- **Migration audit.** All 16 files in `migrations/` verified against the
  dev DB schema — 15 were already applied; `add_mixed_signing_method.sql`
  (per-party e-sign/manual choice, code shipped in `281de8c` but the
  migration was never run) was found pending and applied.
- **`db/dbrb_2026.sql` is stale** — predates every migration (last touched
  before any of them existed). Not regenerated as a fresh consolidated dump;
  instead `DEPLOY.md` and the README document the real path — import it,
  then apply every `migrations/*.sql` file in filename order (all additive/
  idempotent, safe to re-run).
- **Stray `bookings` table** found in the dev DB (162 rows, structurally
  identical to pre-rename `tenancies`, no FK or code referencing it — a
  leftover from re-importing an old dump before `rename_bookings_to_tenancies.sql`
  ran). Dropped.
- **E2E fixture drift.** `tests/e2e_fixtures_seed.sql` only `INSERT IGNORE`s
  its 4 static properties (9001/9002/9010/9011) and 9 fixture users once —
  it never resets state, so repeated runs against the (persistent, not
  disposable-per-run) dev DB had driven those properties/tenancies well past
  the states the suite expects. Reset all rows scoped to landlord-274's
  properties + fixture users 274-282, re-seeded, and found one genuine test
  bug in the process (below) — not an environmental issue.
- **Real test bug found + fixed:** `tests/flow4-housemate-post.spec.js`'s
  `SIGNERS` array for UC-15 only looped the 4 co-tenants, never the
  landlord — `apply_signature()` correctly requires every co-tenant *and*
  the landlord before flipping a tenancy to `active`
  (`contract_next_signer()` in `includes/contracts.php`), so the tenancy
  never left `contract_pending` and UC-16 correctly found the "Add a late
  co-tenant" panel still visible (`agent/case.php` gates it on
  `agent_verifying`/`agent_verified`/`contract_pending`). App behavior was
  correct; the test was incomplete. Fixed by adding `'landlord'` to
  `SIGNERS`. Full suite now 39 passed / 0 failed / 1 pre-existing
  conditional skip (UC-25, agent transfer).
- **New file:** `DEPLOY.md` — env var reference (`RB_DB_*`, `RB_SMTP_*`,
  `GOOGLE_MAPS_API_KEY`, `OPENAI_API_KEY`, `RB_DEBUG`), DB import/migration
  steps, upload-directory ACL notes (`.htaccess` Apache 2.4/`mod_access_compat`
  caveat), HTTPS/session status, pre-deploy test gate, post-deploy smoke
  check.
- **Still open before a real production deploy:** rotate Mailtrap creds;
  point `RB_SMTP_*` at a real transactional sender (Mailtrap never actually
  delivers); restrict `GOOGLE_MAPS_API_KEY` to the production domain in the
  Google Cloud console.

### Found while fixing E2E (two real gaps, not test bugs) — both DONE

- **`/verify.php` doesn't exist.** [DONE] Built as a public, no-login page —
  takes `?ref=<contract_code>`. `contracts/view.php`'s footer note now
  links to the real URL instead of the fictitious `rentbridge.com/verify/<code>`
  path. `tests/flow3-3tenants-wetsign.spec.js` UC-11 un-skipped.
  **Revised after security review** (see below): `generate_contract_code()`
  produces sequential codes (`RB-YYYY-NNNNN`), making this page enumerable by
  anyone with no auth — originally it showed the tenant's full name alongside
  the property, which would let a scraper build a "who lives where" directory.
  Now shows only property type/city, tenancy period, rent, status, and a
  tenant *count* — enough to confirm a contract with that reference genuinely
  exists (the page's actual purpose) without disclosing anyone's identity.
  `faq.php`'s description of the page updated to match.
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


## Option A: Academic-calendar–driven tenancy durations — [DONE for the direct booking flow]

**Goal:** Make tenancy/contract durations match the real UTeM academic calendar instead of approximate month/week arithmetic.

**What was built — `tenancies/new.php` (the student self-book flow):**
1. `includes/academic_terms.php` (new): `get_upcoming_single_terms()`,
   `get_upcoming_academic_years()` (sessions with both a sem1 and sem2 row),
   `get_academic_term($id)`.
2. `migrations/seed_academic_terms_placeholder.sql`: `academic_terms` was
   created empty by the earlier academic-calendar-importer work — nothing had
   ever populated it. Seeded 2 upcoming sessions with realistic-pattern UTeM
   dates (Sem 1 mid-Sept, Sem 2 mid-Feb, short sem late June) as a placeholder;
   `INSERT IGNORE` so importing the real calendar via `admin/academic_calendar.php`
   later just adds to / supersedes it, no conflict.
3. `tenancies/new.php` rebuilt: student picks **"1 Semester"** (choose a specific
   upcoming semester from a dropdown) or **"2 Semesters (Full Year)"** (choose
   an academic-year session; dates span sem1.start_date → sem2.end_date,
   continuous through the break) or **"Custom range"** (free dates, unchanged
   fallback — also what's offered if no upcoming terms exist yet). Dates are
   **resolved server-side** from the `id`/`session` the client sent, never
   trusted from client-supplied dates, for the two term-based options.
4. **Fixed the real `duration_type` enum bug this uncovered**: `tenancies/new.php`
   was inserting `three_semesters`/`four_semesters`/`two_years` — none of which
   match the actual column enum `('1_semester','2_semesters','1_year','custom')`.
   Since this DB's `sql_mode` has no `STRICT_TRANS_TABLES`, MySQL was **silently
   truncating every one of those to `''`** instead of erroring — confirmed live,
   6 existing rows had `duration_type = ''`. Now inserts the correct enum value
   every time (verified: single-term → `1_semester`, academic-year → `2_semesters`,
   custom → `custom`, all with real, correct dates).

**Scoped out (left on the interim week-based approximation, on purpose):**
the agent-mediated flow — `chat/conversation.php`'s term picker,
`chat/send_tenant_form.php`, `student/tenant_form.php` — already inserts
*correct* enum values (unlike `tenancies/new.php` above) via the Option B+C
interim math, and has real Playwright E2E coverage (flows 2–4) riding on its
current behavior. Rewiring all three to real `academic_terms` sessions too is
the same pattern applied here and is a reasonable follow-up, but doing it in
the same pass risked the agent-flow test suite for a second, smaller
correctness gain (that path isn't silently corrupting data — only
`tenancies/new.php` was). Contract term-breakdown clause (build item 4, "Semester
1 + break + Semester 2") also not added — `includes/contracts.php`'s existing
clause 9 (continuous occupancy) still applies since dates are real either way.

Verified: booked one property each via `single_term` / `academic_year` /
`custom`, confirmed correct `duration_type` + dates in the DB for each,
confirmed a tampered/invalid `term_id` is rejected server-side, and confirmed
no bogus row is inserted. PHPUnit (13/13) and the full Playwright suite
(37/39, same 2 justified skips — see `tests/flow6-admin.spec.js` UC-25's
conditional runtime skip, unrelated to this change) both green.
`tests/flow4-housemate-post.spec.js` UC-16 rewritten from "not implemented" to
verify the late-co-tenant form (added earlier this session) is correctly
absent once a tenancy is fully active.

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

## Multi-role support (overlapping roles) — [DONE]

**Goal:** Let one user hold more than one role at the same time (e.g. a student
who is also a landlord). Previously the model was **disjoint + partial**:
authorization read a single `users.primary_role`, so a user was effectively one
role only.

**What was built:**
1. `includes/auth.php`: `get_user_roles()` / `user_has_role()` read
   `user_roles` (falling back to `primary_role` if the table's missing, same
   degrade-safely convention used elsewhere). `require_role($role)` now passes
   if the user holds `$role` at all — even if it's not their currently active
   session role — and calls the new `switch_active_role()` to flip the active
   context (and persist `users.last_used_role`) to match. So navigating to a
   role's pages *is* how you switch into that role. `login_user()` now lands
   the user back in `last_used_role` (if still held) instead of always
   `primary_role`.
2. The 4 functional lookups TODO called out — `includes/agent_assignment.php`,
   `includes/tenancies.php` (2 spots), `includes/transfers.php` (2 spots),
   `includes/reports.php` — now use `EXISTS (SELECT 1 FROM user_roles ...)`
   instead of `primary_role = 'agent'/'admin'`, so a user who holds that role
   as a secondary role is correctly found/notified. (Left alone by design:
   admin's own user-management listings — `admin/agents.php`,
   `admin/landlords.php`, `admin/students.php`, `admin/dashboard.php` counts,
   `admin/statistics/*` — still filter by `primary_role`; those are "primary
   occupation" views for oversight/reporting, and making them multi-role-aware
   is a separate, larger UX decision than this pass covers.)
3. Role switcher: a "Switch to X" link per other-held-role + "Add another
   role" link, added to the user dropdown in `student_layout.php` /
   `landlord_layout.php` / `agent_layout.php`, and to the sidebar footer in
   `admin_layout.php` (which has no dropdown). Backed by `other_user_roles()`.
4. `auth/add_role.php`: self-service "become a student / become a landlord"
   flow for an already-logged-in user — collects the minimum subtype fields,
   inserts the profile row + `user_roles` row, switches active role, redirects
   to that role's dashboard. **Agent is excluded from self-service** — agent
   accounts require UTeM-staff verification via account `status='pending'` at
   registration (`auth/register_agent.php`), and there's no equivalent
   approval gate for an already-active account without risking locking them
   out of their existing role too. Points to contacting admin instead.
5. Admin subtype: decided **not** to add an `admins` table. `user_roles`
   membership already fully represents "is this user an admin", and admin has
   no profile fields (name/phone/etc.) the other subtypes need. Revisit only
   if admin-specific fields are ever needed.
6. All 3 registration forms (`register_student.php`, `register_landlord_step2.php`,
   `register_agent.php`) now call `grant_user_role()` (new — degrades safely,
   same pattern) so newly-registered users get a `user_roles` row going
   forward; `tests/e2e_fixtures_seed.sql` and `tests/php/bootstrap.php` updated
   to match so the test DB stays consistent.

Verified end-to-end against the dev DB: logged in as a single-role student,
added the landlord role via `auth/add_role.php`, confirmed the landlord
dashboard rendered and `last_used_role` updated; navigated back to
`/student/dashboard.php` and confirmed `require_role()` auto-switched back;
logged out and back in and landed in the last-used role. PHPUnit (13/13)
still green; landlord/agent/admin single-role logins unaffected.

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
3. [DONE] **Extend audit coverage** to more tables —
   `migrations/add_audit_log_users_properties.sql` adds AFTER insert/update/
   delete triggers on `users` (email, primary_role, status, last_used_role —
   `password_hash` deliberately excluded from every snapshot) and `properties`
   (landlord_id, title, property_type, monthly_rent, deposit, status,
   assigned_agent_id, agent_status). Applied to dev DB and verified: updating
   either table now writes a row to `audit_log` with the correct actor.
4. [DONE] **Backups** — `backups/backup_db.ps1` runs `mysqldump` (routines +
   triggers + single-transaction) to a timestamped file, verifies the dump is
   non-empty, and prunes dumps older than 30 days. `backups/*.sql` is
   gitignored so dumps never get committed. Not wired to run automatically —
   register it as a daily Windows Task Scheduler job (command in the script's
   header comment) since this box has no cron. Still worth enabling the MySQL
   binary log (`log_bin` in my.ini) for point-in-time recovery beyond daily
   snapshots — that's a server-config change, left alone here.

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
