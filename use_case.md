# RentBridge — Use Case Scenario Table

**Project:** RentBridge FYP — UTeM — Wong Jia Xi (B032310495)
**DB:** `dbrb_2026` | **Base URL:** `http://localhost/rentbridge`
**Last updated:** 2026-06-22

---

## Test Accounts

| Role | Email | Password | Name |
|------|-------|----------|------|
| Student 1 (primary) | s1@test.com | Test@1234 | Ahmad Faris |
| Student 2 (co-tenant) | s2@test.com | Test@1234 | Lim Wei Xian |
| Student 3 (co-tenant) | s3@test.com | Test@1234 | Priya Nair |
| Student 4 (co-tenant) | s4@test.com | Test@1234 | Nurul Ain |
| Student 5 (co-tenant) | s5@test.com | Test@1234 | Tan Jia Hui |
| Student 6 (added late) | s6@test.com | Test@1234 | Hafiz Zulkifli |
| Landlord | ll@test.com | Test@1234 | Encik Roslan |
| Agent | agt@test.com | Test@1234 | Agent Siti |
| Admin | admin@test.com | Test@1234 | Admin RentBridge |

---

## Scenario Table

| UC ID | Flow | Scenario | Actor | Pre-condition | Steps (summary) | Expected Result | Pass Criteria | Status | Notes |
|-------|------|----------|-------|---------------|-----------------|-----------------|---------------|--------|-------|
| UC-01 | Flow 1 | Landlord registers a property | Landlord (`ll@test.com`) | Logged out; at least 1 agent exists | 1. Login → 2. Go to Add Property → 3. Fill all fields (title, type, size, price, deposit, furnishing, address, amenities) → 4. Upload photo + ownership doc → 5. Submit | Success message shown; property in `/landlord/properties.php` with `status = pending_approval`; agent auto-assigned | Property visible in dashboard; `property_agent_assignments` row created | PASS | Property #149 created 2026-06-22; `property_documents` row (ownership_proof, application/pdf); `property_agent_assignments` row (agent_id=16, pending); schema required AUTO_INCREMENT fix on 9 tables before test could run |
| UC-02 | Flow 2A | Student browses and initiates chat | Student 1 (`s1@test.com`) | Pre-seeded approved Whole Unit property exists; no active booking for s1 | 1. Login → 2. Browse listings → 3. Filter Whole Unit → 4. Open property → 5. Click Chat with Landlord → 6. Send inquiry message | Conversation created with `context_type = property_inquiry`; message visible with timestamp | Chat created, message delivered | — | — |
| UC-03 | Flow 2B | Landlord replies and requests contract prep | Landlord (`ll@test.com`) | UC-02 complete; conversation exists | 1. Open conversation → 2. Reply to student → 3. Click Request Contract Preparation | `system_notice` in chat; landlord-agent conversation created with `context_type = contract_prep` | Agent notified via new conversation | — | — |
| UC-04 | Flow 2C | Agent sends Tenant Info Form | Agent (`agt@test.com`) | UC-03 complete; contract prep conversation exists | 1. Open landlord-agent conversation → 2. Click Send Tenant Info Form | `tenant_info_form` message posted in landlord-agent chat; landlord sees the form | Form visible in chat | — | — |
| UC-05 | Flow 2D | Landlord fills Tenant Info Form (1 tenant) | Landlord (`ll@test.com`) | UC-04 complete; form visible in chat | 1. Open form modal → 2. Fill primary tenant details (Ahmad Faris) → 3. No co-tenants → 4. Fill tenancy terms → 5. Submit | Booking row `status = contract_pending`; 1 row in `co_tenants` (primary); success notice in chat | Booking created with correct status | — | — |
| UC-06 | Flow 2E | Agent generates contract (e-sign flow) | Agent (`agt@test.com`) | UC-05 complete; booking `contract_pending` | 1. Click Generate Contract in chat | PDF at `/uploads/contracts/RB-YYYY-NNNNN.pdf`; booking updated with contract ref; `/verify.php?ref=` resolves | PDF generated; verify URL works | — | — |
| UC-07 | Flow 2F | Student 1 e-signs contract | Student 1 (`s1@test.com`) | UC-06 complete; sign page accessible | 1. Navigate to sign page → 2. Draw signature on canvas → 3. Click Sign Contract | `co_tenants.signed_at` populated; `signature_data` has base64 data; booking → `active`; property → `rented` | Booking active, property rented, signature recorded | — | — |
| UC-08 | Flow 3A–B | 3-student tenancy initiated | Student 1 (`s1@test.com`) | Second approved Whole Unit pre-seeded; s1/s2/s3 have no active booking | s1 chats landlord; landlord agrees and requests contract prep | Agent notified in landlord-agent chat | Same as UC-02/03 | — | — |
| UC-09 | Flow 3C | Landlord fills Tenant Info Form (3 tenants) | Landlord (`ll@test.com`) | UC-08 complete; form sent by agent | Fill primary (s1) + 2 co-tenants (s2, s3) → Submit | Booking `contract_pending`; 3 rows in `co_tenants` | 3 co-tenant rows correct | — | — |
| UC-10 | Flow 3D | Agent uploads wet-signed PDF | Agent (`agt@test.com`) | UC-09 complete; physical PDF ready | 1. Generate contract → 2. Upload `signed_contract_test.pdf` via Pending Uploads | `signed_contract_path` populated; `signed_uploaded_at` populated; booking → `active`; property → `rented`; all 3 co_tenants → `signed` | Booking active, PDF path stored | — | — |
| UC-11 | Flow 3E | Public contract verify page | Anyone (no login) | UC-10 complete; signed PDF uploaded | Navigate to `/verify.php?ref=RB-YYYY-NNNNN` | Page loads without login; shows address, tenant names, dates; download link works | Verify page accessible and correct | — | — |
| UC-12 | Flow 4A–B | Student posts for 4 housemates; 3 respond | Students 1–4 | Third approved 4-bedroom Whole Unit pre-seeded | s1 creates co-tenancy post; s2/s3/s4 find it and chat | Post in partners browse page; 3 conversations with `context_type = friend` | Post visible; conversations created | — | — |
| UC-13 | Flow 4C–D | Contract prep for 4 tenants | Landlord + Agent | UC-12 complete | Same contract prep flow; landlord fills 4 tenants | Booking `contract_pending`; 4 rows in `co_tenants` | 4 co-tenant rows | — | — |
| UC-14 | Flow 4F | Add 5th tenant (Student 5) before signing | Admin (`admin@test.com`) | UC-13 complete; no signatures yet | Admin adds s5 to booking; agent regenerates contract | 5th row in `co_tenants`; PDF lists 5 tenants | PDF updated | — | — |
| UC-15 | Flow 4G | All 5 students e-sign | Students 1–5 | UC-14 complete; sign pages accessible | Each student draws and submits signature | All 5 `co_tenants.signed_at` populated; booking → `active`; property → `rented` | All signatures recorded | — | — |
| UC-16 | Flow 4H | Add Student 6 after booking active (edge case) | Agent + Student 6 | UC-15 complete; booking `active` | Agent adds s6 to booking; s6 signs via e-sign | 6th `co_tenants` row with `signed_at`; booking remains `active`; property remains `rented` | Late addition does not reset booking | — | — |
| UC-17 | Flow 5A–B | Agent receives assignment and accepts property | Agent (`agt@test.com`) | Property with `status = pending_approval` pre-seeded | 1. Login → dashboard → 2. Click Review → 3. Check photos/docs → 4. Click Accept | `agent_status = accepted`; system_notice posted in landlord-agent chat | Agent acceptance recorded | — | — |
| UC-18 | Flow 5C–D | Agent proposes inspection; landlord confirms | Agent + Landlord | UC-17 complete | Agent sends 2 time slots; landlord selects slot 1, sets access method, ticks consent | `property_inspections` row `status = scheduled`; `consent_given_at` populated | Inspection scheduled | — | — |
| UC-19 | Flow 5E–F1 | Agent marks inspection complete and approves listing | Agent (`agt@test.com`) | UC-18 complete | Click Mark Inspection Complete → Click Approve Listing | `property_inspections.status = completed`; `properties.status = available`; `agent_verified_at` populated | Listing live and available | — | — |
| UC-20 | Flow 5F2 | Agent rejects listing (separate test) | Agent (`agt@test.com`) | Property at `pending_approval` | Click Reject → enter reason → Submit | `properties.status = rejected`; `property_agent_assignments.outcome = rejected`; reason stored | Rejection with reason stored | — | — |
| UC-21 | Flow 6A | Admin login and dashboard overview | Admin (`admin@test.com`) | Seed data from Flows 1–5 exists | Login → assert redirect to `/admin/dashboard.php`; check counts visible | Dashboard shows user/property/booking counts; no PHP errors | Dashboard loads cleanly | — | — |
| UC-22 | Flow 6B | Admin views user analytics and exports CSV | Admin | UC-21 complete | Navigate to user stats page; check charts; click Export CSV | Charts render (12-month, doughnut, bar); CSV downloads with required headers | Analytics correct; CSV valid | — | — |
| UC-23 | Flow 6C | Admin manages users | Admin | Users seeded | Search user → view detail; attempt delete of user with active booking | User detail loads; delete blocked with error message | Role/booking guard enforced | — | — |
| UC-24 | Flow 6D–E | Admin views properties and bookings | Admin | Seed data exists | Browse properties with status filter; open booking with co-tenants | Pending filter works; co-tenant table shown; contract verify URL clickable | Data integrity visible in admin | — | — |
| UC-25 | Flow 6F | Admin processes agent transfer request | Admin | Pending transfer request seeded | Find request → Force Assign → select agent → Submit | `agent_transfer_requests.status = completed`; `properties.assigned_agent_id` updated; new `property_agent_assignments` row with `assignment_type = transfer` | Transfer processed correctly | — | — |
| UC-26 | Flow 6H | Role guard and edge case checks | Any | Logged out / wrong role | Access admin while logged out; access student dashboard as admin; invalid property/booking IDs; fake contract ref | Redirects to login; access denied; 404/not-found messages; no PHP fatal errors | No exposed errors, guards enforced | — | — |

---

## Test Run Log

| Run # | Date | Tester | Flows Covered | Pass | Fail | Blocked | Notes |
|-------|------|--------|---------------|------|------|---------|-------|
| 1 | 2026-06-22 | Playwright MCP | Flow 1 (UC-01) | 1 | 0 | 0 | UC-01 PASS. Property #149 pending_approval; doc uploaded; agent auto-assigned. Schema fixes needed: 9 tables missing AUTO_INCREMENT (properties, PAA, property_documents, property_images, notifications, bookings, messages, conversations, co_tenants). |

---

## Known Gotchas

- **Schema bug (fixed):** 9 tables missing `AUTO_INCREMENT` in the original `dbrb_2026.sql` — `properties`, `property_agent_assignments`, `property_documents`, `property_images`, `notifications`, `bookings`, `messages`, `conversations`, `co_tenants`. Applied `ALTER TABLE ... MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)` plus set correct `AUTO_INCREMENT` values before any tests can run.
- Messages table is `messages`, not `chat_messages`
- `user_a` must have lower `user_id` than `user_b` in `conversations` table
- `CAST(? AS JSON)` fails in MariaDB — use plain `?` binding
- `toggle_saved.php` must be at project root, not `/student/`
- Poll fires every 5s — wait 6s after sending before asserting the other party sees it
- Modal ID: `tenantInfoModal` (landlord) vs `coTenantFormModal` (student) — do not mix
- After agent resends Tenant Info Form, previous booking and `co_tenants` rows are wiped
- `co_tenants.signature_data` stores base64 — assert non-null, not a specific value
