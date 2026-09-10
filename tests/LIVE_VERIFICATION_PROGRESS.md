# Live mixed-signing verification — in-progress state (NOT committed, contains test creds)

Goal: reproduce the 4-tenant mixed-signing scenario on the LIVE InfinityFree site
(http://rentbridge.infinityfreeapp.com) to confirm the `includes/contracts.php`
`apply_signature()` null-notify fix (guard on `$next['user_id'] !== null`) works in
production. This mirrors `tests/flow7-mixed-signing.spec.js`, which passes locally
(13/13) — see task output `biqdgudru.output` for the clean local run. An earlier
local run (`b8z5jezdc.output`) failed at UC-27g3 with a stale assertion
(`toHaveCount(0)` on "Signing a physical copy" instead of checking `Signed \d`
count=5) — that assertion was already fixed in the current spec file per the
session summary; re-verify `tests/flow7-mixed-signing.spec.js` lines ~323-337 still
have the corrected assertions before trusting a future local re-run.

## Live test fixtures created so far

- Landlord: `testll.mixsign@rentbridge-test.local` / `TestPass@123`
  - owns properties #26, #27, #28, #29 (throwaways, used only to load up other
    agents' caseload so a controllable agent becomes the deterministic pick)
  - owns property #30 "Mixed Signing Real Test Unit" (whole_unit, RM900/mo,
    RM1800 deposit) — the REAL test property
- Agent: `testagent.mixsign@utem.edu.my` / `TestPass@123`
  - user id #37, "Test Agent MixSign", staff ID AGT999, dept FTMK
  - approved by admin; accepted/inspected/approved property #30 → status "available"
- 4 students, all password `TestPass@123`:
  - `mixsign.s1@student.utem.edu.my` — Test Student One, matric B03MS0001, IC 030101010001, phone 0111000001
  - `mixsign.s2@student.utem.edu.my` — Test Student Two, matric B03MS0002, IC 030101010002, phone 0111000002
  - `mixsign.s3@student.utem.edu.my` — Test Student Three, matric B03MS0003, IC 030101010003, phone 0111000003
  - `mixsign.s4@student.utem.edu.my` — Test Student Four, matric B03MS0004, IC 030101010004, phone 0111000004

## Progress on the live site

1. Chat conversation id=9 opened between student1 and agent about property #30. Done.
2. Agent sent tenant info form: rent RM900, deposit RM1800, term "24 months (2 years)", start date 2026-09-20. Done.
3. Student1 opened `/student/tenant_form.php?form_id=25&conv_id=9&property_id=30`, filled all
   4 tenants (primary + 3 co-tenants: Test Student Two/Three/Four with matching NRIC/phone/email
   per the fixtures list above), clicked "Submit tenant details". Page confirmed
   "Form submitted — Your tenant details have been sent to the agent." DONE.
4. Logged out student1, logged in as agent (`testagent.mixsign@utem.edu.my` / `TestPass@123`) —
   landed on `/agent/dashboard.php`. DONE.
5. Navigated to chat conversation id=9 as the agent, saw "Tenant info submitted ... 4 tenants ·
   Tenancy #24", clicked "Generate contract" → landed on `/agent/case.php?id=24`.
6. **NEW BUG FOUND (unrelated to the notify() fix):** contract generation failed with
   "Failed to generate contract: Failed to render the contract PDF." This is a live-host-only
   failure (works fine locally) — root-caused to mPDF's `tempDir` being set to
   `sys_get_temp_dir()` in `rb_render_agreement_pdf()` (`includes/contracts.php` ~line 860),
   which on InfinityFree points to a system temp path PHP can't actually write to, so mPDF's
   font/temp cache setup fails silently.
   - FIX APPLIED LOCALLY (not yet uploaded to live site): changed `tempDir` to
     `__DIR__ . '/../uploads/mpdf_tmp'` (created if missing — inside the app's own writable
     uploads tree), and changed the catch block to rethrow the real mPDF exception message
     instead of swallowing it to `null`/a generic string, so if this doesn't fully fix it the
     real cause will show up directly in the flash message on `/agent/case.php?id=24`.
   - **ACTION NEEDED FROM USER:** upload the updated `includes/contracts.php` to the live
     InfinityFree site (same file, same path), then retry "Generate contract PDF" on
     `/agent/case.php?id=24` as the agent. If it still fails, the flash message will now show
     the real underlying mPDF error — paste that back and it can be root-caused further.
7. Root cause of the PDF-generation bug turned out to be a stalled deployment, not a code bug:
   my first `includes/contracts.php` re-upload never actually landed on the live host (confirmed
   via a `_diag.php` diagnostic page comparing live file size/markers vs local — see that file,
   still on the server, DELETE IT when done, it exposes filesystem info). A second re-upload of
   the same file fixed it. `agent/generate_contract.php` still carries a leftover
   `CANARY_V1` debug string in its RuntimeException message (harmless, but should be cleaned up
   before this branch is considered done — search for "CANARY_V1").
8. Contract generated successfully: contract code **RB-2026-00001**, tenancy #24 status
   "Contract pending" / contract status "Awaiting signed upload". Agent case page
   (`/agent/case.php?id=24`) now shows a "Download PDF" link and an "Upload signed copy" file
   input — this looks like the AGENT-side manual "upload one combined signed PDF" path. Need to
   find the STUDENT/LANDLORD-side `contracts/view.php?id=<contractId>` e-sign link (used in the
   past mixed-signing spec) to actually run the mixed sequence — check chat notifications or
   student/landlord tenancy page for the link with the real contract id (not tenancy id 24).
9. Contract view/sign URL found: **contract id = 12** (`/contracts/view.php?id=12`,
   `/contracts/sign.php?id=12`) — NOT the tenancy id (24). Signing order shown on the contract:
   Landlord, then Primary Tenant, then 3 Co-Tenants (order in the "Parties" list; actual
   enforced order differs slightly — see below).
10. Mixed signing progress so far (all via `/contracts/sign.php?id=12`, e-sign done by drawing
    a scribble on the `<canvas>` with raw page.mouse events since it's a signature pad, not a
    form field):
    - student1 (mixsign.s1) — **e-signed** successfully at 20:23. No crash.
    - student2 (mixsign.s2) — clicked "I'd rather sign a physical copy" + confirmed browser
      dialog — now shows "Signing a physical copy". No crash.
    - student3 (mixsign.s3) — **e-signed** successfully at 20:24. No crash.
    - student4 (mixsign.s4) — chose "physical copy" — now shows "Signing a physical copy".
      No crash.
    - Contract view now says "Waiting for Landlord to sign." — landlord is last.
11. **CRITICAL STEP DONE — BUG FIX CONFIRMED WORKING IN PRODUCTION.** Logged in as landlord,
    went to `/contracts/sign.php?id=12`, e-signed (last e-signer, with student2 and student4
    still pending manual/physical). Result: **NO crash, no 500 error.** Redirected cleanly to
    `/contracts/view.php?id=12`, which now shows:
    "Everyone who's e-signing has signed. Waiting on your agent to collect the remaining
    physical signature(s) and upload the merged copy."
    This is the exact `awaiting_manual` code path that used to crash pre-fix (`notify()` being
    called with a null `$next['user_id']` when `contract_next_signer()` returns the
    'awaiting_manual'/'all_done' role). Screenshot saved:
    `live-flow7-05-awaiting-manual-NO-CRASH.png`. **This was the core objective of the whole
    live verification exercise and it is now confirmed fixed on rentbridge.infinityfreeapp.com.**
12. Optional remaining steps (nice-to-have, not required to confirm the fix):
    - Agent uploads a merged signed PDF for tenancy #24 / contract #12 via
      `/agent/case.php?id=24` ("Upload signed copy" section) to see the fully-resolved
      "all Signed" end state (mirrors local flow7's UC-27g1/g2/g3).
13. Cleanup TODO before calling this fully done:
    - Remove `CANARY_V1` debug marker from `agent/generate_contract.php` (local file already
      has it; needs a clean re-upload without the marker, or leave as-is since it's harmless —
      just noisy in error messages).
    - Delete `_diag.php` from the live server (it exposes filesystem info — do this regardless
      of whether the optional step 12 is done).
    - Local files with fixes not yet re-synced anywhere else: `includes/contracts.php` (2 fixes:
      mpdf tempDir + output-dir throw) and `agent/generate_contract.php` (canary marker) — both
      already uploaded and confirmed live, just noting for any future local/live diff check.

## Remaining steps after contract generation

6. Mixed signing in this EXACT order (this sequence is what previously crashed
   the live site before the `includes/contracts.php` fix):
   - student1 (mixsign.s1) → e-sign
   - student2 (mixsign.s2) → choose manual/physical
   - student3 (mixsign.s3) → e-sign
   - student4 (mixsign.s4) → choose manual/physical
   - landlord (testll.mixsign) → e-sign  ← this step is where the crash used to
     happen (last e-signer completing while manual signers still pending
     triggered `notify()` with a null user_id)
6. Verify NO crash / 500 error on the landlord's e-sign step.
7. Verify contract view shows "awaiting_manual" state, 2x "Signing a physical
   copy" badges, no e-sign links for the 2 manual co-tenants.
8. Agent uploads the merged signed PDF for the property/tenancy.
9. Verify final resolved state: all parties show "Signed", tenancy/contract
   active, "Signing a physical copy" badges still present (describes method,
   not completion) but no outstanding action items.
10. Take screenshots at key steps (user explicitly asked: "remember to screenshot").

## How to resume

Log back into the site as each role above (all live/production, not local
XAMPP) and continue from step 3 (fill Co-tenant 2 + add/fill Co-tenant 3 +
submit) using Playwright MCP browser tools against
`http://rentbridge.infinityfreeapp.com`.
