# Backend test suite (PHPUnit)

Automated backend tests for RentBridge's business logic — the contract
lifecycle, agent-commission maths, and the audit-log triggers. These complement
the Playwright **E2E** flows in `tests/*.spec.js` (which cover the UI).

## Safety
The suite runs against a **throwaway `dbrb_2026_test` database**, rebuilt from
`dbrb_2026.sql` + the new migrations on every run (see `bootstrap.php`).
It never touches your development database (`dbrb_2026`).

## Prerequisites
- MySQL/MariaDB running (XAMPP).
- The `mysql` client reachable. On XAMPP/Windows `C:\xampp\mysql\bin\mysql.exe`
  is auto-detected; otherwise set `RB_MYSQL` to its path.
- PHPUnit installed via Composer.

## Install & run
```bash
composer install            # installs phpunit (require-dev)
composer test               # == vendor/bin/phpunit --configuration phpunit.xml
# or directly:
vendor/bin/phpunit
```

## Configuration (env vars, all optional)
| Var | Default | Purpose |
|-----|---------|---------|
| `RB_DB_HOST` | localhost | test DB host |
| `RB_DB_USER` | root | test DB user |
| `RB_DB_PASS` | *(empty)* | test DB password |
| `RB_MYSQL` | XAMPP path or `mysql` | mysql client used to build the test DB |

`RB_DB_NAME` is forced to `dbrb_2026_test` by the bootstrap.

## What's covered
- `ContractCommissionTest` — contract creation & status transitions, standard
  terms text, duplicate-contract guard, commission = 1 month rent + 6% SST,
  idempotent commission creation.
- `AuditLogTest` — insert/update on `contracts` and insert on `agent_commissions`
  produce `audit_log` rows with the correct old/new snapshots.

## Extending
Add more `*Test.php` files under `tests/php/`. Use seeded fixtures from
`dbrb_2026.sql` (e.g. user 2 = student, 10 = landlord, 15 = agent, property 1).
