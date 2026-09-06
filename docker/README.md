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

   Two gotchas hit while first running this against real Aiven MySQL (as
   opposed to the MariaDB this schema was developed against):
   - An older/bundled `mysql` client may fail to authenticate at all with
     `Plugin caching_sha2_password could not be loaded` — that's the client
     missing the plugin, not a real auth failure. A PHP script using PDO/
     mysqli (mysqlnd) doesn't have this problem, so import that way instead
     if you hit it.
   - Aiven's default `sql_mode` includes `ANSI_QUOTES` — double-quoted
     strings are identifiers there, not string literals, unlike a typical
     MariaDB default. Only matters for your own ad-hoc queries against this
     DB; nothing in this repo's SQL files uses double-quoted strings.
5. Do **not** import `db/seed_data.sql` or `tests/e2e_fixtures_seed.sql`.

## 2. Render web service

Render has no native PHP runtime — this repo's `Dockerfile` (PHP 8.2 +
Apache, `gd`/`mysqli`/`pdo_mysql`/`zip` extensions, `.htaccess` overrides
enabled for `uploads/*/.htaccess` access control) handles that.

1. New Web Service on Render, connect this repo, runtime = **Docker**
   (picks up the root `Dockerfile` automatically). Free plan is fine. When
   creating the service, **explicitly select "Docker"** as the runtime —
   don't let it auto-detect, since this repo also has a `package.json` (for
   the Playwright test suite only) that Render's auto-detection will pick
   over the Dockerfile, trying to run the app as a Node project instead.
2. **Do NOT use Render's "Secret Files"** for the CA cert. On the free tier
   they're mounted with permissions that even a root-uid process inside the
   container can't read (`is_readable()` returns false, `PDO` fails with a
   generic `Cannot connect to MySQL using SSL` — confirmed via a temporary
   diagnostic endpoint during initial setup). Instead: base64-encode the CA
   cert and pass it as a plain environment variable — `docker/entrypoint.sh`
   decodes it to a file the container creates itself at startup, which it
   can always read regardless of that platform quirk.
   ```
   base64 -w0 aiven-ca.pem
   ```
   (a CA cert is a public value, not a secret in the usual sense — safe to
   paste as a plain env var value.)
3. **Environment -> Environment Variables**: set the vars listed in
   `render.yaml` — at minimum `RB_DB_HOST`, `RB_DB_PORT`, `RB_DB_NAME`,
   `RB_DB_USER`, `RB_DB_PASS` (from Aiven), `RB_BASE_PATH=` (empty — Render
   serves from the domain root, same as InfinityFree), and
   `RB_DB_SSL_CA_B64=<output of the base64 command above>`. Do **not** also
   set `RB_DB_SSL_CA` directly — the entrypoint script sets that itself
   after decoding. Add the SMTP/Maps/OpenAI vars from `DEPLOY.md` §1 as
   needed.
4. Deploy. Render assigns the container a `PORT` env var at runtime;
   `docker/entrypoint.sh` rewrites Apache's config to listen on it, and
   decodes `RB_DB_SSL_CA_B64` to `/tmp/certs/aiven-ca.pem`, before Apache
   starts — no manual port or cert-file config needed on the host.

## 3. Post-deploy

Same smoke check as `DEPLOY.md` §8 — register a test account through each
role, run one tenancy to a signed contract, confirm the PDF renders,
confirm a real email arrives. Given the storage caveat above, also confirm
you understand any photos/contracts uploaded during testing will disappear
on the next deploy.
