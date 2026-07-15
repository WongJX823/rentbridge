# RentBridge

**RentBridge** is a web-based rental platform that connects **university students** (UTeM, Melaka) with **landlords**, with every tenancy witnessed and physically verified by a **UTeM staff agent**. It is built around the realities of student renting — semester-aligned tenancies, shared housing ("Find Housemate"), and trusted, in-person property verification.

> Final Year Project (FYP). Not affiliated with or endorsed by Universiti Teknikal Malaysia Melaka.

---

## Key Features

### For Students
- Browse and search property listings; save favourites.
- Request a tenancy with **semester-based durations** (1 semester ≈ 18 weeks; 2 semesters ≈ 9 months, inclusive of the semester break) or a custom range.
- **Find Housemate** — post a listing to recruit co-tenants, apply to others' posts, and chat as a group.
- Review and **e-sign** the tenancy contract; track tenancy status end-to-end.

### For Landlords
- List and manage properties (images, ownership documents, pricing).
- Review and respond to tenancy requests.
- Sign contracts digitally and track active tenancies.

### For Agents (UTeM staff)
- Accept assigned cases and perform **physical property verification** (inspection checklist + photos).
- Generate the formal tenancy agreement and witness signing.
- Track **commission** earnings (one month's rent + 6% SST) and handle **case transfers**.

### For Admins
- Manage users, properties, tenancies, contracts, agent transfers.
- Review reports/moderation and platform contact messages.

### Platform
- Multi-party **contract generation** (formal tenancy agreement PDF) with embedded e-signatures; co-tenants without an account can wet-sign the printed copy.
- In-app **messaging**, **notifications**, a lightweight friend system, and **reporting/moderation**.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP (PDO, no framework) |
| Database | MySQL / MariaDB (`dbrb_2026`) |
| Front-end | HTML, Bootstrap 5, vanilla JS |
| PDF | [dompdf](https://github.com/dompdf/dompdf), [mPDF](https://mpdf.github.io/) |
| Email | [PHPMailer](https://github.com/PHPMailer/PHPMailer) |
| Server | Apache (XAMPP) |

---

## Project Structure

```
rentbridge/
├── config/          # database.php (DB credentials)
├── includes/        # shared logic + per-role layouts, auth, mailer, contracts, etc.
├── auth/            # register / login / password / avatar
├── admin/           # admin console
├── agent/           # agent portal (cases, inspections, contracts, earnings, transfers)
├── landlord/        # landlord portal (properties, tenancies)
├── student/         # student portal (dashboard, housemates, tenancies)
├── chat/            # conversations & tenant/co-tenant forms
├── contracts/       # contract view / sign
├── tenancies/       # tenancy creation
├── api/             # small JSON endpoints (notifications, reports)
├── assets/          # css / js / images
├── uploads/         # user-uploaded files (images, documents, signatures, contracts)
├── migrations/      # incremental SQL migrations
├── dbrb_2026.sql    # database schema (+ reference data)
├── seed_data.sql    # sample/seed data
└── vendor/          # Composer dependencies
```

---

## Getting Started

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL/MariaDB, PHP 8.1+)
- [Composer](https://getcomposer.org/)

### Installation

1. **Clone into your XAMPP web root**
   ```bash
   cd C:/xampp/htdocs
   git clone https://github.com/WongJX823/rentbridge.git
   cd rentbridge
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Create the database** and import the schema (via phpMyAdmin or CLI):
   ```bash
   mysql -u root -e "CREATE DATABASE dbrb_2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root dbrb_2026 < dbrb_2026.sql
   # optional sample data:
   mysql -u root dbrb_2026 < seed_data.sql
   ```
   Then apply any newer migrations in `migrations/` as needed.

4. **Configure the app**
   - `config/database.php` — set `DB_HOST`, `DB_NAME`, and DB credentials.
   - `includes/mail_config.php` — set SMTP details for email/notifications.

5. **Run**
   - Start **Apache** and **MySQL** in XAMPP.
   - Visit **http://localhost/rentbridge/**

---

## Documentation & Diagrams

- `FYP.drawio` — Entity-Relationship Diagram (ERD) and UML class diagram, split by module.
- `SiteMap.drawio` — navigation flow / site map.
- Open these in [draw.io / diagrams.net](https://app.diagrams.net/).

---

## Roadmap / Next Steps

Planned work, roughly in priority order. See `TODO.md` for the full detail.

- [ ] **Academic-calendar–driven tenancy durations.** Add an `academic_terms` table seeded from UTeM's official calendar so "1 semester" / "2 semesters" resolve to the *actual* semester start/end dates instead of approximate week counts. *(Interim fix already applied: 1 sem ≈ 18 weeks, 2 sem ≈ 9 months incl. the semester break, and contracts state the continuous period.)*
- [ ] **Fix the `tenancies.duration_type` enum mismatch.** The booking form stores keys like `semester_4` / `academic_8`, but the column enum expects `1_semester` / `2_semesters` / `1_year` / `custom` — map the form key to the enum value on insert.
- [ ] **Property status progress bar.** Show a landlord/agent status tracker: `pending → awaiting inspection → inspection complete → available`.
- [ ] **Single-source the contract template.** Refactor `agent/generate_contract.php` to use the shared `rb_agreement_html()` builder (the signed-download PDF already does), so the blank and signed agreements can never drift.
- [ ] **Magic-link e-signing for co-tenants without an account.** Let account-less co-tenants sign via a tokenised link instead of wet-signing a printed copy.
- [ ] **Verify signed-contract signatures render** across environments (mPDF image path handling) with an end-to-end signed test contract.

### Recently completed
- Semester-accurate durations + contract clause covering the semester break.
- Signed contract download now renders the **formal tenancy agreement** (not the summary card) with e-signatures embedded on the signature lines.
- Agent commission accounting (one month's rent + 6% SST) with backfill for legacy contracts.

---

## License

This project is developed for academic purposes as a Final Year Project. All rights reserved by the author.
