# RentBridge — Deploy Runbook

Target: shared/VPS hosting (Apache + PHP 8.1+ + MySQL/MariaDB), production.
This is the checklist for taking the current dev setup live for the first time.

Deploying to Render (Docker) + Aiven MySQL instead of traditional shared
hosting? See `docker/README.md` — same underlying config (env vars below
still apply), plus Docker/TLS/port specifics for that combination.

## 1. Environment variables

Nothing below is hardcoded as a required secret in code — nothing after this
runbook should be committed to git. Set these on the host (Apache
`SetEnv` / `.htaccess` / PHP-FPM pool / control-panel "PHP env vars" UI —
whichever your host supports):

| Variable | Purpose | Falls back to (dev only) |
|---|---|---|
| `RB_DB_HOST` | MySQL host | `localhost` |
| `RB_DB_NAME` | MySQL database name | `dbrb_2026` |
| `RB_DB_USER` | MySQL user | `root` |
| `RB_DB_PASS` | MySQL password | empty |
| `RB_DEBUG` | Set to `1` to show real DB-connection errors instead of a generic message. **Leave unset in production.** | unset |
| `RB_BASE_PATH` | URL path prefix the app is served under. Local XAMPP serves it from a `/rentbridge/` subfolder; a host that serves it from the domain root (e.g. InfinityFree) needs this set to `` (empty). | `/rentbridge` |
| `GOOGLE_MAPS_API_KEY` | Interactive map picker (Maps JS API) | key in git-ignored `config/google.php` if present |
| `OPENAI_API_KEY` | Academic-calendar PDF importer (admin-only tool) | key in git-ignored `config/openai.php` if present |
| `OPENAI_MODEL` | Vision model for the importer | `gpt-4o` |
| `RB_SMTP_HOST` / `RB_SMTP_PORT` / `RB_SMTP_USERNAME` / `RB_SMTP_PASSWORD` / `RB_SMTP_ENCRYPTION` / `RB_SMTP_FROM_EMAIL` / `RB_SMTP_FROM_NAME` | Outbound email (verification codes, contract links, notifications) | Mailtrap sandbox creds in git-ignored `includes/mail_config.php` if present |

**Before going live:**
- If the host has no env-var UI (e.g. InfinityFree), edit the `BASE_PATH`
  fallback directly in `includes/auth.php` on the uploaded copy — change
  `'/rentbridge'` to `''` when serving from the domain root. Every internal
  link, redirect, and asset path in the app is built from this constant; if
  it's wrong, pages load with no CSS and every internal link 404s (the
  homepage still renders because it needs no internal links, which is why
  this is easy to miss until you click something).
- Point `RB_SMTP_*` at a real transactional sender (Mailtrap is a sandbox —
  emails never actually reach recipients). Use a real provider (SES, Postmark,
  a real SMTP account, etc.).
- Restrict `GOOGLE_MAPS_API_KEY` to the production domain in the Google Cloud
  console (HTTP referrer restriction) — an unrestricted key is billable by
  anyone who finds it in page source.
- The Mailtrap credentials that used to live in `includes/mail_config.php`
  were committed to this repo's git history while the repo was public.
  Rotate them in the Mailtrap dashboard if not already done, independent of
  moving to real prod SMTP creds.

## 2. Database

> If phpMyAdmin's import ever throws `Unexpected beginning of statement... near
> "phpMyAdmin" at position 0` on one of these files, it picked up a UTF-8 BOM
> before the leading `--` comment (Windows SQL export tools do this). Already
> fixed in the three files that had it as of this writing; if it recurs on an
> edited file, strip the first 3 bytes (`EF BB BF`) and re-save as UTF-8 without BOM.

> Free-tier shared MySQL (e.g. InfinityFree) commonly doesn't grant the
> `TRIGGER` privilege to customer DB users — `add_audit_log.sql` and
> `add_audit_log_users_properties.sql` will fail with `#1142 - TRIGGER
> command denied`. No app code reads `audit_log` (only triggers write to it,
> nothing queries it), so this is safe to work around: import only the
> `CREATE TABLE audit_log` statement from `add_audit_log.sql` (skip its
> trigger block), and skip `add_audit_log_users_properties.sql` entirely (it's
> 100% triggers). The audit trail just stays empty on such hosts — nothing
> else breaks. If the host does support triggers, run both files unmodified.

1. Create the database: `CREATE DATABASE dbrb_2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Import the base schema: `mysql -u <user> -p dbrb_2026 < db/dbrb_2026.sql`
3. Apply every file in `migrations/`, **in filename order** (they're additive/idempotent — safe to re-run):
   ```
   for f in migrations/*.sql; do mysql -u <user> -p dbrb_2026 < "$f"; done
   ```
   (`db/dbrb_2026.sql` predates all current migrations — step 3 is not optional.)
4. Do **not** import `db/seed_data.sql` or `tests/e2e_fixtures_seed.sql` into
   production — those are sample/test data only.

## 3. File permissions / uploads

- `uploads/` subfolders already ship `.htaccess` files that deny direct web
  access to contracts, signatures, and property documents (they're served
  only through the PHP endpoints that check authorization) and block script
  execution in `avatars/`/`signed_contracts/`. **Verify these still work on
  the target server** — they use Apache 2.2-style `Order deny,allow` /
  `Deny from all`, which needs `mod_access_compat` enabled on Apache 2.4 (on
  by default on most hosts, but some hardened/managed configs disable it —
  test a direct URL to a file under `uploads/contracts/` returns 403, not the
  PDF).
- The web server user needs write access to `uploads/` (and its
  subdirectories) and to `backups/` if you enable the backup script there.

## 4. Backups

- `backups/backup_db.ps1` runs `mysqldump` and prunes dumps older than 30
  days. It's PowerShell/Windows-oriented — if the target host is Linux, port
  the same logic to a shell script + cron entry rather than trying to run
  PowerShell on the server. Either way, schedule it to run daily.
- Consider enabling MySQL binary logging (`log_bin`) for point-in-time
  recovery beyond daily snapshots, if the host allows it.

## 5. HTTPS / sessions

Already handled in code — nothing to change, just confirm at the host level:
- `includes/auth.php` sets the session cookie `Secure` only when the request
  is actually HTTPS (checks `$_SERVER['HTTPS']`), so this correctly turns on
  once the site is served over HTTPS. Just make sure the production vhost
  actually terminates HTTPS (host's free cert / Let's Encrypt / etc.) — if it
  doesn't, sessions still work but without the `Secure` flag.

## 6. PHP error display

- Production PHP should have `display_errors = Off` in `php.ini` (most hosts
  default to this). The one place in code that used to force errors on
  unconditionally (`contracts/sign.php`) has been removed — there's no
  remaining code path that re-enables `display_errors` at runtime.
- DB connection failures show a generic message unless `RB_DEBUG=1` is set
  (see §1) — don't set it in production.

## 7. Pre-deploy test gate

Run both suites clean before deploying:
```
composer test         # PHPUnit backend suite
npx playwright test   # E2E suite (needs Apache + MySQL running locally first)
```

## 8. Post-deploy smoke check

- Register a test student/landlord/agent account, list a property, run one
  tenancy through to a signed contract, confirm the PDF renders with
  signatures, confirm a verification-code email actually arrives (proves SMTP
  creds are real, not Mailtrap sandbox).
- Hit a direct URL under `uploads/contracts/` and `uploads/property_docs/`
  and confirm you get a 403, not the file (see §3).
