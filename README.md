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
├── migrations/      # incremental SQL migrations (apply in filename order after db/dbrb_2026.sql)
├── db/              # dbrb_2026.sql (base schema), seed_data.sql (sample data)
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
   mysql -u root dbrb_2026 < db/dbrb_2026.sql
   # then apply every migration, in filename order (additive/idempotent, safe to re-run):
   for f in migrations/*.sql; do mysql -u root dbrb_2026 < "$f"; done
   # optional sample data:
   mysql -u root dbrb_2026 < db/seed_data.sql
   ```

4. **Configure the app** — all of these can be set via environment variable instead (see `DEPLOY.md` for the full list); for local dev the simplest path is a git-ignored local file:
   - `config/database.php` — set `DB_HOST`, `DB_NAME`, and DB credentials (or `RB_DB_*` env vars).
   - `includes/mail_config.php` — set SMTP details for email/notifications (or `RB_SMTP_*` env vars). Not present by default; create it (same shape as `config/google.php`) if you need real email locally — the app falls back to empty/sandbox values otherwise.

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

See `TODO.md` for full detail and `DEPLOY.md` for the deploy runbook. As of
the current schedule snapshot, core dev/data-integrity/testing/security work
is done; what's left is deploy prep → deploy → UAT.

### Recently completed
- Academic-calendar–driven tenancy durations for the direct booking flow, and the `duration_type` enum data-corruption bug that surfaced while building it.
- Property status progress bar, property map pinpoint, multi-role account support, audit log + soft-delete + backups, per-party mixed e-sign/manual contract signing, gender/race listing preferences.
- Semester-accurate durations + contract clause covering the semester break.
- Signed contract download renders the **formal tenancy agreement** (not the summary card) with e-signatures embedded on the signature lines.
- Agent commission accounting (one month's rent + 6% SST) with backfill for legacy contracts.

---

## License

This project is developed for academic purposes as a Final Year Project. All rights reserved by the author.
