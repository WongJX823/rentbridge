# RentBridge — Test Case Generation Prompt

Paste this entire prompt into Claude (or use as Playwright MCP instructions).
Replace all `[PLACEHOLDERS]` with your actual test credentials before running.

---

## SYSTEM CONTEXT

You are generating manual test cases (or Playwright automation scripts) for
**RentBridge** — a 4-role PHP/MySQL student rental platform running locally at
`http://localhost/rentbridge` on XAMPP. DB: `dbrb_2026`.

**Roles:** student, landlord, agent, admin
**Stack:** PHP 8.0 + MariaDB + Bootstrap 5.3

**Test accounts (pre-seed these before running):**

| Role    | Email                          | Password     | Name             |
|---------|-------------------------------|--------------|------------------|
| Student 1 (primary) | s1@test.com        | Test@1234    | Ahmad Faris      |
| Student 2 (co-tenant) | s2@test.com      | Test@1234    | Lim Wei Xian     |
| Student 3 (co-tenant) | s3@test.com      | Test@1234    | Priya Nair       |
| Student 4 (co-tenant) | s4@test.com      | Test@1234    | Nurul Ain        |
| Student 5 (co-tenant) | s5@test.com      | Test@1234    | Tan Jia Hui      |
| Student 6 (added late) | s6@test.com     | Test@1234    | Hafiz Zulkifli   |
| Landlord | ll@test.com                   | Test@1234    | Encik Roslan     |
| Agent    | agt@test.com                  | Test@1234    | Agent Siti       |
| Admin    | admin@test.com                | Test@1234    | Admin RentBridge |

**Test property (pre-seed one approved available listing):**
- Type: Whole Unit, 4-bedroom
- Address: No. 12, Jalan Dahlia 3, Taman Bukit Tambun Perdana, Durian Tunggal
- Price: RM1,200/month, deposit: RM2,400
- Status: available, assigned_agent_id = agt@test.com account

---

## FLOW 1 — LANDLORD REGISTERS A PROPERTY

**Objective:** Landlord lists a new property end-to-end and it reaches `pending_approval`.

**Pre-condition:** Logged out. At least one agent account exists.

**Steps:**

1. Navigate to `http://localhost/rentbridge`
2. Click Login → enter `ll@test.com` / `Test@1234` → submit
3. Assert: redirected to landlord dashboard
4. Click "Add Property" or navigate to `/landlord/add_property.php`
5. Fill in property details:
   - Title: `Bilik Sewa Dekat UTeM — Taman Muzaffar`
   - Type: Room
   - Size: 100 sqft
   - Price: RM380/month
   - Deposit: RM760
   - Furnishing: Fully Furnished
   - Address: No. 5, Jalan Muzaffar 7, Taman Muzaffar Heights, Ayer Keroh
   - Google Maps URL: any valid maps.google.com URL for that area
   - Amenities: WiFi, Air-conditioning, Water Heater (tick checkboxes)
   - Distance to UTeM: 3.2 km
6. Upload at least 1 property photo (JPG under 5MB)
7. Upload at least 1 ownership document (type: geran/IC/utility)
8. Click Submit
9. Assert: success message shown, property appears in `/landlord/properties.php`
   with status `pending_approval`
10. Assert: `assigned_agent_id` is populated in DB (agent auto-assigned)
11. Assert: row exists in `property_agent_assignments` table for this property

**Expected DB state after:**
```
properties.status = 'pending_approval'
properties.agent_status = 'pending'
property_agent_assignments: 1 row, outcome = 'pending'
```

**Pass criteria:** Property visible in landlord dashboard, agent notified.

---

## FLOW 2 — 1 STUDENT BROWSES AND SIGNS CONTRACT (E-SIGN)

**Objective:** Single student finds a property, chats landlord, goes through full
contract flow and signs digitally via e-sign canvas.

**Pre-condition:** Use pre-seeded approved Whole Unit property.
Landlord and Agent accounts ready. Student 1 (s1@test.com) has no active booking.

### PART A — Student Browses and Inquires

1. Login as `s1@test.com`
2. Navigate to `/listings.php`
3. Filter by Type: Whole Unit
4. Click on the pre-seeded test property
5. Assert: property detail page loads with price, photos, map
6. Assert: "Chat with Landlord" button visible (not "Chat with Agent")
7. Click "Chat with Landlord"
8. Assert: conversation created, redirected to `/chat/conversation.php?id=X`
9. Assert: `context_type = 'property_inquiry'` in DB
10. Send message: "Hi, saya berminat dengan unit ini. Boleh saya tahu lebih lanjut?"
11. Assert: message appears in chat with sent_at timestamp

### PART B — Landlord Requests Contract Preparation

12. Open new browser tab, login as `ll@test.com`
13. Navigate to Messages / Conversations
14. Open conversation with Student 1 about the test property
15. Reply: "Boleh, unit masih available. Bila nak pindah?"
16. Assert: Student 1 sees reply in chat (poll picks it up within 5s)
17. Landlord clicks "Request Contract Preparation" yellow bar button
18. Assert: system_notice appears in chat: "Contract preparation requested"
19. Assert: new landlord-agent conversation created with `context_type = 'contract_prep'`
20. Assert: system_notice posted in landlord-agent chat

### PART C — Agent Sends Tenant Info Form

21. Open new tab, login as `agt@test.com`
22. Navigate to agent dashboard or messages
23. Open conversation with landlord about the test property
24. Assert: contract prep request visible as system_notice
25. Click "Send Tenant Info Form"
26. Assert: `tenant_info_form` message type posted in landlord-agent chat
27. Assert: landlord sees the form in their chat

### PART D — Landlord Fills Tenant Info Form

28. Switch to landlord tab
29. Open the tenant info form modal
30. Fill Primary Tenant details:
    - Full Name: Ahmad Faris (pre-fill from student profile)
    - IC Number: 021103-14-5678
    - Phone: 011-23456789
    - Email: s1@test.com
    - Home Address: No. 3, Jalan Melati, Kuala Lumpur
31. No co-tenants (this is 1-person flow)
32. Fill Tenancy Terms:
    - Start Date: [today + 7 days]
    - End Date: [today + 187 days] (6 months)
    - Duration: 1_semester
    - Monthly Rent: RM1,200
    - Deposit: RM2,400
33. Submit form
34. Assert: booking row created with `status = 'contract_pending'`
35. Assert: co_tenants has 1 row (primary = 1) for this booking
36. Assert: success notice in chat

### PART E — Agent Generates Contract

37. Switch to agent tab
38. Assert: "Generate Contract" button now visible in the chat
39. Click "Generate Contract"
40. Assert: PDF generated at `/uploads/contracts/RB-YYYY-NNNNN.pdf`
41. Assert: booking row updated with contract reference
42. Assert: verify URL works: `/verify.php?ref=RB-YYYY-NNNNN`

### PART F — Student Signs via E-Sign Canvas

43. Switch to student tab (s1@test.com)
44. Navigate to `/contracts/sign.php` or follow link from booking/chat
45. Assert: contract details shown (property, dates, rent)
46. Draw signature on canvas
47. Click "Sign Contract"
48. Assert: `co_tenants.signed_at` populated for Student 1
49. Assert: `co_tenants.signature_data` contains base64 canvas data
50. Assert: booking status transitions to `active`
51. Assert: property status transitions to `rented`

**Pass criteria:** Booking active, property rented, signature recorded.

---

## FLOW 3 — 3 STUDENTS BROWSE, SIGN CONTRACT (NO POST, WET SIGN PDF UPLOAD)

**Objective:** Landlord-led flow where 3 students share a whole unit.
No partner matching post used. Agent uploads physically signed PDF (not e-sign).

**Pre-condition:** Second approved Whole Unit property pre-seeded.
Students 1, 2, 3 available. No existing booking between them.

### PART A — Student 1 Initiates

1. Login as `s1@test.com`
2. Find second pre-seeded whole unit on listings
3. Click "Chat with Landlord"
4. Send: "Kami bertiga nak sewa unit ni. Boleh discuss?"

### PART B — Landlord Negotiates and Requests Contract

5. Login as `ll@test.com`
6. Open conversation with Student 1
7. Reply agreeing to tenancy
8. Click "Request Contract Preparation"
9. Assert: agent notified in landlord-agent chat

### PART C — Agent Sends and Landlord Fills Form (3 Tenants)

10. Login as `agt@test.com`
11. Send Tenant Info Form to landlord
12. Switch to landlord tab
13. Open form modal
14. Fill Primary Tenant: Student 1 details (Ahmad Faris, IC, phone, email)
15. Add Co-Tenant 1:
    - Full Name: Lim Wei Xian
    - IC: 021205-10-1234
    - Phone: 012-3456789
    - Email: s2@test.com
    - Home Address: No. 8, Jalan Wawasan, Penang
16. Add Co-Tenant 2:
    - Full Name: Priya Nair
    - IC: 021308-07-9876
    - Phone: 013-9876543
    - Email: s3@test.com
    - Home Address: No. 15, Jalan Harmoni, Selangor
17. Fill Tenancy Terms: 6 months, RM1,200/month, RM2,400 deposit
18. Submit
19. Assert: booking row `contract_pending`
20. Assert: `co_tenants` has 3 rows — 1 primary + 2 co-tenants

### PART D — Agent Generates and Uploads Signed PDF

21. Switch to agent tab
22. Click "Generate Contract"
23. Assert: PDF generated (watermarked, unsigned version)
24. [OFFLINE STEP — simulate]: Print, wet-sign, scan back to a PDF file
    Use any test PDF file named `signed_contract_test.pdf` (min 50KB)
25. Navigate to agent dashboard → "Pending Uploads" section
26. Find this booking
27. Click "Upload Signed Contract"
28. Upload `signed_contract_test.pdf`
29. Assert: `bookings.signed_contract_path` populated
30. Assert: `bookings.signed_uploaded_at` populated
31. Assert: `bookings.status` → `active`
32. Assert: property status → `rented`
33. Assert: all 3 `co_tenants` rows → `status = 'signed'`

### PART E — Verify Public Contract Page

34. Navigate to `/verify.php?ref=RB-YYYY-NNNNN`
35. Assert: page loads without login
36. Assert: shows property address, tenant names, booking dates
37. Assert: "Download Signed Contract" link works

**Pass criteria:** 3-person booking active, PDF uploaded, verify URL resolves.

---

## FLOW 4 — 1 STUDENT POSTS FOR 4, SIGNS CONTRACT, ADDS 1 MORE (6 TOTAL, E-SIGN)

**Objective:** Student 1 creates a partner matching post looking for 3 housemates.
Students 2, 3, 4 respond. Whole unit booked for 4. After contract generated,
Student 5 is added (admin/agent action). Then Student 6 is added (6 total). All e-sign.

**Pre-condition:** Third approved Whole Unit (4-bedroom) pre-seeded.
All 6 student accounts available.

### PART A — Student 1 Posts for Housemates

1. Login as `s1@test.com`
2. Navigate to `/student/find_housemates.php` or Partners section
3. Create a co-tenancy post:
   - Property interest: [select third pre-seeded whole unit]
   - Looking for: 3 more housemates
   - Budget: RM300/person/month
   - Move-in: [today + 14 days]
   - Description: "Cari housemate untuk unit 4 bilik dekat UTeM. Serius sahaja."
4. Submit post
5. Assert: post appears in `/student/partners.php` browse page

### PART B — Students 2, 3, 4 Find and Connect

6. Login as `s2@test.com`
7. Browse find_housemates page
8. Find Student 1's post
9. Click contact/chat button
10. Send: "Saya berminat. Boleh join?"
11. Repeat steps 6-10 for `s3@test.com` and `s4@test.com`
12. Assert: 3 separate conversations between s1 and s2/s3/s4 created
    with `context_type = 'friend'`

### PART C — Student 1 Chats Landlord for Group

13. Login as `s1@test.com`
14. Navigate to the third whole unit property page
15. Click "Chat with Landlord"
16. Send: "Kami berlima berminat nak sewa unit 4 bilik ni."
17. Assert: conversation created

### PART D — Contract Prep (4 Tenants)

18. Follow same PART B and C steps from Flow 3
19. Landlord fills form with:
    - Primary: Student 1 (Ahmad Faris)
    - Co-Tenant 1: Student 2 (Lim Wei Xian)
    - Co-Tenant 2: Student 3 (Priya Nair)
    - Co-Tenant 3: Student 4 (Nurul Ain)
20. Submit → Assert: booking `contract_pending`, 4 rows in `co_tenants`

### PART E — Agent Generates Contract

21. Agent clicks "Generate Contract"
22. Assert: PDF lists all 4 tenants
23. Assert: verify URL resolves

### PART F — Student 5 Added Before Signing

24. Login as admin (`admin@test.com`)
25. Navigate to Bookings → find this booking
26. Add co-tenant (or agent adds via chat):
    - Full Name: Tan Jia Hui
    - IC: 030512-14-2345
    - Phone: 014-5678901
    - Email: s5@test.com
27. Assert: 5th row added to `co_tenants`
28. Agent regenerates contract
29. Assert: PDF now lists all 5 tenants

### PART G — All 5 E-Sign

30. Login as `s1@test.com` → navigate to sign page → draw and submit signature
31. Assert: co_tenants row for s1 → `signed_at` populated
32. Repeat for `s2@test.com`, `s3@test.com`, `s4@test.com`, `s5@test.com`
33. Assert: all 5 co_tenant rows have `signed_at` and `signature_data`
34. Assert after last signature: booking → `active`, property → `rented`

### PART H — Student 6 Added After Activation (Edge Case)

35. Login as agent (`agt@test.com`)
36. Navigate to booking detail for this booking
37. Add late co-tenant:
    - Full Name: Hafiz Zulkifli
    - IC: 031101-12-6789
    - Phone: 016-7890123
    - Email: s6@test.com
38. Assert: 6th row in `co_tenants`, `status = 'pending'`
39. Login as `s6@test.com` → sign via e-sign canvas
40. Assert: s6 co_tenant row → `signed_at` populated
41. Assert: booking remains `active` (adding co-tenant post-activation doesn't reset)
42. Assert: property remains `rented`

**Pass criteria:** 6 signatures recorded, booking active, all co_tenant rows have correct status.

---

## FLOW 5 — AGENT FULL FLOW

**Objective:** Agent handles the complete lifecycle — receive assignment,
schedule inspection, verify property, generate contract, upload signed PDF.

**Pre-condition:** A new property just submitted by landlord (status: `pending_approval`,
agent_status: `pending`). Landlord-agent conversation auto-created.

### PART A — Agent Receives Assignment Notification

1. Login as `agt@test.com`
2. Navigate to agent dashboard
3. Assert: property appears in "Pending Reviews" section
4. Assert: property shows landlord name, property type, submitted date

### PART B — Agent Reviews and Accepts Property

5. Click "Review" on the pending property
6. Navigate to `/agent/property_review.php?id=X`
7. Assert: property photos visible
8. Assert: ownership documents downloadable
9. Assert: pricing benchmark shown
10. Click "Accept"
11. Assert: `agent_status` → `accepted` in DB
12. Assert: landlord-agent conversation created (or found)
13. Assert: system_notice posted: "Agent accepted — propose inspection time"

### PART C — Agent Proposes Inspection Schedule

14. Open landlord-agent conversation
15. Click "Send Inspection Schedule" (or compose `inspection_schedule_request`)
16. Fill:
    - Proposed Date/Time 1: [tomorrow 10:00 AM]
    - Proposed Date/Time 2: [day after tomorrow 2:00 PM]
    - Note: "Saya boleh datang pagi atau petang. Sila pilih masa yang sesuai."
17. Send
18. Assert: `inspection_schedule_request` message type in messages table

### PART D — Landlord Confirms Inspection

19. Switch to landlord tab (`ll@test.com`)
20. Open landlord-agent conversation
21. Assert: inspection schedule proposal visible
22. Select Slot 1 (tomorrow 10:00 AM)
23. Select access method: `landlord_present`
24. Tick consent: "Saya memberi kebenaran kepada ejen RentBridge untuk menjalankan
    pemeriksaan hartanah ini bagi pihak saya."
25. Submit
26. Assert: `property_inspections` row created with `status = 'scheduled'`
27. Assert: `consent_given_at` populated
28. Assert: system_notice in chat: "Inspection confirmed: [date], Access: Landlord Present"

### PART E — Agent Marks Inspection Complete

29. Switch back to agent tab
30. Navigate to `/agent/property_review.php?id=X` or inspection button in chat
31. Click "Mark Inspection Complete"
32. Assert: `property_inspections.status` → `completed`
33. Assert: `property_inspections.completed_at` populated

### PART F — Agent Approves or Rejects Listing

**Scenario F1 — Approve:**
34. Click "Approve Listing"
35. Assert: `properties.status` → `available`
36. Assert: `agent_verified_at` and `agent_verified_by` populated
37. Assert: landlord receives notification

**Scenario F2 — Reject (test separately):**
34. Click "Reject Listing"
35. Enter rejection reason: "Dokumen geran tidak sepadan dengan alamat hartanah."
36. Submit
37. Assert: `properties.status` → `rejected`
38. Assert: `property_agent_assignments.outcome` → `rejected`
39. Assert: rejection reason stored

### PART G — Agent Handles Contract (reference Flow 2 Part E)

40. After a student booking reaches `contract_pending`
41. Login as agent, open landlord-agent conversation
42. Click "Generate Contract"
43. Assert: PDF generated with correct tenant details
44. Optionally: upload signed PDF (reference Flow 3 Part D)

**Pass criteria:** Inspection row complete, listing approved, contract generated.

---

## FLOW 6 — ADMIN FULL FLOW

**Objective:** Admin monitors system health, manages users, reviews analytics,
handles a pending agent transfer request, and accesses booking details.

**Pre-condition:** Seed data from all previous flows exists.
At least one pending agent transfer request in DB.

### PART A — Admin Login and Dashboard Overview

1. Navigate to `http://localhost/rentbridge`
2. Login as `admin@test.com` / `Test@1234`
3. Assert: redirected to `/admin/dashboard.php`
4. Assert: dashboard shows counts — total users, properties, active bookings
5. Assert: no PHP errors or warnings visible

### PART B — User Analytics

6. Navigate to `/admin/statistics/users.php`
7. Assert: 12-month growth chart renders (Chart.js or similar)
8. Assert: role doughnut chart shows student/landlord/agent/admin split
9. Assert: university bar chart renders
10. Click "Export CSV"
11. Assert: CSV file downloads with correct headers
    (id, name, email, role, created_at minimum)

### PART C — User Management

12. Navigate to Users list in admin panel
13. Search/filter for a specific student by name
14. Assert: user detail page loads with role, email, registration date
15. [If suspension implemented]: test suspend/unsuspend toggle
16. Assert: admin cannot delete a user with an active booking
    (attempt delete → error shown, not silent)

### PART D — Properties Overview

17. Navigate to `/admin/properties.php`
18. Assert: all properties listed with status badges
19. Filter by status: `pending_approval`
20. Assert: only pending properties shown
21. Click one property
22. Navigate to `/admin/property.php?id=X`
23. Assert: assigned agent shown, assignment history visible

### PART E — Bookings Management

24. Navigate to `/admin/bookings.php`
25. Assert: bookings list with tenant aggregation shown
    (primary tenant name + co-tenant count)
26. Click a booking with co-tenants
27. Navigate to `/admin/booking.php?id=X`
28. Assert: primary tenant details visible
29. Assert: co-tenants table shows all tenants with signed/pending status
30. If booking has signed PDF: assert download link works
31. Assert: contract verify URL displayed and clickable

### PART F — Agent Transfer Request Dashboard

32. Navigate to admin dashboard or `/admin/transfer_requests.php`
33. Assert: pending transfer request appears
    showing: property name, current agent, requester, reason, batch number
34. Click "Force Assign" on the transfer request
35. Select an agent from dropdown
36. Submit
37. Assert: `agent_transfer_requests.status` → `completed`
38. Assert: `properties.assigned_agent_id` updated to new agent
39. Assert: new row in `property_agent_assignments` with
    `assignment_type = 'transfer'`

### PART G — Messages Inbox

40. Navigate to `/admin/messages.php`
41. Assert: contact form submissions listed
42. Click one message
43. Assert: sender IP, message body, submitted_at shown
44. [If reply implemented]: reply and assert sent via Mailtrap sandbox

### PART H — System Edge Case Checks

45. Attempt to access `/admin/dashboard.php` while logged out
    → Assert: redirected to login page (not error)
46. Attempt to access `/student/dashboard.php` while logged in as admin
    → Assert: redirected or access denied (role guard working)
47. Navigate to a non-existent property: `/admin/property.php?id=99999`
    → Assert: 404 page or "Property not found" message, not PHP fatal error
48. Navigate to public verify with fake ref: `/verify.php?ref=RB-0000-00000`
    → Assert: "Contract not found" message shown, no DB error

**Pass criteria:** All analytics render, transfer request processed,
role guards enforced, no fatal errors on edge inputs.

---

## CHECKLIST BEFORE RUNNING ALL FLOWS

- [ ] All 9 test accounts seeded in `users` table with correct `role` values
- [ ] 3 pre-approved whole unit properties seeded with `status = 'available'`
- [ ] 1 property seeded with `status = 'pending_approval'` for agent flow
- [ ] At least 1 pending agent transfer request seeded for admin flow
- [ ] `uploads/contracts/signed/` directory exists and is writable
- [ ] Mailtrap sandbox credentials in `includes/mail_config.php` are active
- [ ] mPDF vendor package installed (`vendor/mpdf/mpdf`)
- [ ] No existing conflicting conversations between test accounts
- [ ] DB backed up before running flows (in case of mid-test corruption)

---

## KNOWN GOTCHAS DURING TESTING

- `messages` table not `chat_messages` — check queries if chat fails
- `user_a` must be lower user_id than `user_b` — if conversation not found,
  check ID ordering in `conversations` table
- `CAST(? AS JSON)` will fail in MariaDB — use plain `?` binding
- `toggle_saved.php` must be at project ROOT not in /student/
- Poll endpoint fires every 5s — wait 6s after sending a message before
  asserting the other party sees it
- Modal ID: use `tenantInfoModal` for landlord-side form,
  `coTenantFormModal` for old student-side form — don't mix them
- After agent resends tenant info form, previous booking and co_tenant
  rows are wiped — do not resend unless testing that specific wipe behavior
- e-sign canvas data saves as base64 in `co_tenants.signature_data` —
  assert this field is non-null, not that it equals a specific value

---

*Generated for RentBridge FYP — UTeM — Wong Jia Xi (B032310495)*
*Last updated: 2026-06-22*
Remember to do scenario table, create a new file call use_case to store record.