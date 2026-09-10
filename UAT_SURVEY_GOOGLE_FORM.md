# RentBridge UAT & Usability Survey — Google Forms build spec

Copy this structure section-by-section into Google Forms. Section titles below match
what you should name each Forms "section" (the grey page-break cards). Question types
map directly to Forms' question-type dropdown.

---

## Form title & description (top of the form)

**Title:** RentBridge — User Acceptance Testing & Usability Survey

**Description:**

> Thank you for helping test RentBridge, a rental platform built for UTeM students,
> landlords, and agents. This survey has two parts:
>
> 1. **A short set of real tasks** you'll carry out on the live site (~10–15 min)
> 2. **Two short usability questionnaires** about your experience (~10–12 min total)
>
> There are no right or wrong answers — please answer honestly. Your responses are
> anonymous unless you choose to leave your name, and are used only to evaluate
> RentBridge for a Final Year Project. It should take about 20–25 minutes in total.
>
> **Before you start:** make sure you have access to the live RentBridge site and
> know which role you'll be testing (Student, Landlord, Agent, or Admin).

Settings to enable on the form:
- **Collect email addresses:** Off (keep anonymous) — or "Verified" only if you need
  to link responses back to specific testers for follow-up.
- **Response validation:** enable "Require a response in each row" on every grid
  question (see below) so SUS/USE scoring stays valid.
- **Progress bar:** On.
- **Shuffle question order:** Off (order matters for the task flow).

---

## Section 1 — Consent & participant info

**Section description:** "A couple of quick details before you begin."

**Q1. Do you agree to take part in this usability test?**
- Type: Multiple choice
- Options:
  - Yes, I agree to take part
  - No, I do not wish to take part
- Required: Yes
- Response validation / branching: if "No", go to a final "Thanks anyway" section
  that ends the form (Forms: "Go to section based on answer").

**Q2. Your name or a participant code (optional)**
- Type: Short answer
- Required: No
- Help text: "e.g. P1 — leave blank to stay anonymous"

*(Q3–Q9 below are participant background — kept BEFORE the role branch so every
tester answers them. Used only to segment results, e.g. compare first-time vs
experienced renters.)*

**Q3. Gender**
- Type: Multiple choice
- Options: Male · Female · Prefer not to say
- Required: No

**Q4. Age group**
- Type: Multiple choice
- Options: Under 18 · 18–20 · 21–23 · 24–26 · Over 26
- Required: No

**Q5. What best describes you?**
- Type: Multiple choice
- Options: UTeM student · Other university student · Landlord / property owner ·
  Property agent · Other
- Required: No

**Q6. Have you rented or searched for student accommodation before?**
- Type: Multiple choice
- Options: Yes, several times · Yes, once · No, this is my first time
- Required: No

**Q7. Which rental / property websites or apps have you used before?**
- Type: Checkboxes (select all that apply)
- Options: Mudah.my · iBilik · PropertyGuru · Facebook / WhatsApp groups ·
  University housing portal · Other (with "Other" free-text box on) ·
  None — I haven't used any before
- Required: No

**Q8. How comfortable are you using websites and apps in general?**
- Type: Multiple choice (or linear scale 1–5)
- Options: Not comfortable · Slightly comfortable · Moderately comfortable ·
  Very comfortable · Extremely comfortable
- Required: No

**Q9. Which device are you testing RentBridge on today?**
- Type: Multiple choice
- Options: Phone · Tablet · Laptop / desktop
- Required: No

**Q10. Which role are you acting as today?**
- Help text: "Most testers act as a Tenant or a Landlord. Agent and Admin are
  internal staff roles — only choose them if you were asked to test that side."
- Type: Multiple choice
- Options:
  - Tenant (student looking for housing)
  - Landlord (offering a property)
  - Agent (staff)
  - Admin (staff)
- Required: Yes
- Branching (Forms: "Go to section based on answer"):
  - Tenant (student looking for housing) → Section 2 (Tenant / student tasks)
  - Landlord (offering a property)       → Section 3 (Landlord tasks)
  - Agent (staff)                        → Section 4 (Agent tasks)
  - Admin (staff)                        → Section 5 (Admin tasks)

---

## Section 2 — Tenant / student tasks

*(Reached when Q10 = "Tenant (student looking for housing)". After this section:
"Go to Section 6 — SUS Questionnaire")*

**Section description:** "You're acting as a student looking for housing. Carry out
each task on the live site, then mark it done or not."

For each task below, use:
- Type: Multiple choice
- Options: **Completed** / **Attempted but stuck** / **Skipped**
- Required: Yes

1. Search or browse listings and open one property's details
2. Set your gender and race in your profile (Profile → edit)
3. On the listings page, turn on "Matching my gender / race" and confirm listings restricted to a different gender/race disappear
4. Start a chat with the property's agent or landlord
5. Create a "Find housemates" post for a property, choosing how many housemates and a preferred housemate gender & race
6. Browse housemate posts and apply to join one that fits you
7. Fill in the tenant information form when asked (IC, phone, etc.)
8. Open the generated contract and sign it (draw your e-signature)
9. Confirm the tenancy shows as active on your dashboard

**Q. Anything confusing, broken, or worth flagging? (optional)**
- Type: Paragraph
- Required: No

---

## Section 3 — Landlord tasks

*(After this section: "Go to Section 6 — SUS Questionnaire")*

Same question type/options pattern as Section 2 (Completed / Attempted but stuck / Skipped):

1. Register a landlord account and log in
2. List a new property with at least one photo and one ownership document
3. While listing, pin the property's exact location using the map pin (drawer) — OR paste a Google Maps link and confirm it fills the address
4. Set a preferred tenant gender and race for the listing (or leave as "Any")
5. Track your listing's status as it moves through inspection
6. Reply in chat when the agent proposes an inspection time
7. Sign the tenancy contract once a student has applied

**Q. Anything confusing, broken, or worth flagging? (optional)** — Paragraph, not required.

---

## Section 4 — Agent tasks

*(After this section: "Go to Section 6 — SUS Questionnaire")*

Same pattern:

1. Accept a property case assigned to you
2. Propose inspection time slots to the landlord in chat
3. Complete the inspection checklist and approve (or reject) the listing
4. Generate a tenancy contract once a student's form is submitted
5. For a contract with mixed signing, collect a physical signature and upload the merged PDF

**Q. Anything confusing, broken, or worth flagging? (optional)** — Paragraph, not required.

---

## Section 5 — Admin tasks

*(After this section: "Go to Section 6 — SUS Questionnaire")*

Same pattern:

1. Review the dashboard's summary counts (users, properties, tenancies)
2. Search for a user and open their profile
3. Open a property's detail page and check its tenancy record
4. Process an agent transfer request from start to finish
5. Look up a made-up contract reference on the public verify page (should show a clean "not found")

**Q. Anything confusing, broken, or worth flagging? (optional)** — Paragraph, not required.

---

## Section 6 — SUS Questionnaire

**Section description:**

> The next 10 statements are about RentBridge as a whole. For each, choose how much
> you agree — 1 = Strongly disagree, 5 = Strongly agree. *(System Usability Scale,
> Brooke, 1986)*

Use **one Multiple choice grid question** (recommended — keeps SUS scoring simple and
compact) with:
- Columns: `1`, `2`, `3`, `4`, `5`
- Column sub-labels (add as text near the grid, Forms doesn't support column captions):
  note "1 = Strongly disagree · 5 = Strongly agree" in the section description above.
- Rows (in this exact order — order matters for scoring):
  1. I think that I would like to use this system frequently.
  2. I found the system unnecessarily complex.
  3. I thought the system was easy to use.
  4. I think that I would need the support of a technical person to be able to use this system.
  5. I found the various functions in this system were well integrated.
  6. I thought there was too much inconsistency in this system.
  7. I would imagine that most people would learn to use this system very quickly.
  8. I found the system very cumbersome to use.
  9. I felt very confident using the system.
  10. I needed to learn a lot of things before I could get going with this system.
- Grid setting: **"Require a response in each row"** = On (SUS scoring requires all 10 answered)

**Scoring (do this later in Sheets, not in the form itself):**
Odd rows (1,3,5,7,9): score = answer − 1. Even rows (2,4,6,8,10): score = 5 − answer.
Sum all 10, multiply by 2.5 → SUS score out of 100. Benchmark average = 68.

---

## Section 7 — USE Questionnaire: Usefulness

**Section description:**

> The remaining statements cover four aspects of RentBridge, one section each. Rate
> each from 1 (Strongly disagree) to 7 (Strongly agree), or choose N/A if a statement
> truly doesn't apply. *(USE Questionnaire, Lund, 2001)*

**One Multiple choice grid question:**
- Columns: `1`, `2`, `3`, `4`, `5`, `6`, `7`, `N/A`
- Rows:
  1. It helps me be more effective.
  2. It helps me be more productive.
  3. It is useful.
  4. It gives me more control over the activities in my life.
  5. It makes the things I want to accomplish easier to get done.
  6. It saves me time when I use it.
  7. It meets my needs.
  8. It does everything I would expect it to do.
- Grid setting: "Require a response in each row" = On

---

## Section 8 — USE Questionnaire: Ease of Use

**One Multiple choice grid question**, same columns (`1`–`7`, `N/A`):
1. It is easy to use.
2. It is simple to use.
3. It is user friendly.
4. It requires the fewest steps possible to accomplish what I want to do with it.
5. It is flexible.
6. Using it is effortless.
7. I can use it without written instructions.
8. I don't notice any inconsistencies as I use it.
9. Both occasional and regular users would like it.
10. I can recover from mistakes quickly and easily.
11. I can use it successfully every time.

---

## Section 9 — USE Questionnaire: Ease of Learning

**One Multiple choice grid question**, same columns (`1`–`7`, `N/A`):
1. I learned to use it quickly.
2. I easily remember how to use it.
3. It is easy to learn to use it.
4. I quickly became skillful with it.

---

## Section 10 — USE Questionnaire: Satisfaction

**One Multiple choice grid question**, same columns (`1`–`7`, `N/A`):
1. I am satisfied with it.
2. I would recommend it to a friend.
3. It is fun to use.
4. It works the way I want it to work.
5. It is wonderful.
6. I feel I need to have it.
7. It is pleasant to use.

---

## Section 11 — Final thoughts

**Q. Any other feedback about RentBridge? (optional)**
- Type: Paragraph
- Required: No

**Q. Would you use RentBridge again in future?**
- Type: Multiple choice
- Options: Definitely / Probably / Not sure / Probably not / Definitely not
- Required: No

**Ending section message:** "Thank you for testing RentBridge! Your feedback directly shapes the final version of this project."

---

## "Thanks anyway" section (only reached if Q1 = No)

**Section description:** "No problem — thanks for your time. You can close this tab now."
(No further questions; set this as the form's exit point for that branch.)

---

## After collecting responses

- Google Forms auto-creates a linked Sheet ("Responses" tab → green Sheets icon).
- For grid questions, each row's answer appears in Sheets as one column per statement
  — copy those into the same SUS/USE scoring spreadsheet you use for the UAT Kit
  artifact's CSV exports, so both distribution channels (this form + the artifact
  link) feed one combined dataset.
- SUS and USE dimension formulas: replicate the scoring logic from the RentBridge
  UAT Kit artifact (https://claude.ai/code/artifact/3c794434-3df4-426e-91ba-51ddf2d7bb2a)
  — same items, same order, same formulas — so results from both channels are directly comparable.
