# Deploying to Render + Aiven MySQL

## Storage caveat (read first)

This app writes uploaded property photos, signed contract PDFs, signature
images, and ownership documents to `uploads/` at runtime. Render's default
web-service disk is **ephemeral** — everything written there is wiped on
every redeploy, and on every restart (including free-tier spin-down after
15 minutes of inactivity). We're deliberately accepting this for now: fine
for a demo/testing phase, **not** safe for real users until either a paid
Render persistent disk is attached, or uploads move to external object
storage (S3-compatible, e.g. Cloudflare R2).

## 1. Aiven MySQL

1. Create a MySQL service on Aiven (free trial or hobbyist plan).
2. From the service overview page, note: **Host**, **Port** (not 3306 —
   Aiven assigns a random port), **User**, **Password**, default database
   name (or create `dbrb_2026` explicitly).
3. Download the service's CA certificate (**"CA Certificate"** download link
   on the service overview page) — save it locally as `aiven-ca.pem`. Aiven
   enforces TLS; this cert is required to connect.
4. Import the schema. Aiven doesn't ship phpMyAdmin — use the `mysql` CLI
   with the CA cert:
   ```
   mysql --host=<host> --port=<port> --user=<user> --password \
         --ssl-ca=aiven-ca.pem dbrb_2026 < db/dbrb_2026.sql
   mysql --host=<host> --port=<port> --user=<user> --password \
         --ssl-ca=aiven-ca.pem dbrb_2026 < migrations/add_academic_terms.sql
   mysql --host=<host> --port=<port> --user=<user> --password \
         --ssl-ca=aiven-ca.pem dbrb_2026 < deploy_combined_infinityfree.sql
   ```
   (Same three-file order as the InfinityFree/GoogieHost deploys — see
   `DEPLOY.md` §2 for why `add_audit_log_users_properties.sql` is skipped.
   Aiven's managed MySQL *does* support `TRIGGER`/`CREATE PROCEDURE`, so if
   you want the full audit trail, you can additionally run the untouched
   `migrations/add_audit_log_users_properties.sql` and swap in the original
   `migrations/add_soft_delete_and_restrict_cascade.sql` instead of the
   rewritten block in `deploy_combined_infinityfree.sql` — optional.)
5. Do **not** import `db/seed_data.sql` or `tests/e2e_fixtures_seed.sql`.

## 2. Render web service

Render has no native PHP runtime — this repo's `Dockerfile` (PHP 8.2 +
Apache, `gd`/`mysqli`/`pdo_mysql`/`zip` extensions, `.htaccess` overrides
enabled for `uploads/*/.htaccess` access control) handles that.

1. New Web Service on Render, connect this repo, runtime = **Docker**
   (picks up the root `Dockerfile` automatically). Free plan is fine.
2. **Environment -> Secret Files**: add a secret file with path
   `/etc/secrets/aiven-ca.pem` and paste the contents of the `aiven-ca.pem`
   you downloaded from Aiven. This keeps the cert out of git.
3. **Environment -> Environment Variables**: set the vars listed in
   `render.yaml` — at minimum `RB_DB_HOST`, `RB_DB_PORT`, `RB_DB_NAME`,
   `RB_DB_USER`, `RB_DB_PASS` (from Aiven), plus `RB_BASE_PATH=` (empty —
   Render serves from the domain root, same as InfinityFree) and
   `RB_DB_SSL_CA=/etc/secrets/aiven-ca.pem`. Add the SMTP/Maps/OpenAI vars
   from `DEPLOY.md` §1 as needed.
4. Deploy. Render assigns the container a `PORT` env var at runtime;
   `docker/entrypoint.sh` rewrites Apache's config to listen on it before
   starting — no manual port config needed.

## 3. Post-deploy

Same smoke check as `DEPLOY.md` §8 — register a test account through each
role, run one tenancy to a signed contract, confirm the PDF renders,
confirm a real email arrives. Given the storage caveat above, also confirm
you understand any photos/contracts uploaded during testing will disappear
on the next deploy.
