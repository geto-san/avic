# AVIC — Claims Management System

A simplified claims platform for motor insurance in Uganda, modelled on **Guidewire ClaimCenter**.
Policyholders report a loss (First Notice of Loss); adjusters investigate, set reserves, issue
payments and close the claim — with every step audited.

Built for a university course project: plain PHP 8.1+, MySQL/MariaDB, server-rendered HTML/CSS and
almost no JavaScript. No framework, no Composer dependencies.

## What it does

| ClaimCenter concept | In AVIC |
|---|---|
| FNOL with policy search & retrieval | 3-step wizard: find policy → loss details & exposures → review & submit |
| Coverage verification / validation rules | Automated at intake; questions are **flagged**, not blocking (see below) |
| Assignment rules | Ordered rules in `config/rules.php`: specialist in region → anyone in region → anyone; least-loaded wins; otherwise the unassigned queue |
| Activities & work plan | Rules create tasks per event (claim opened, exposure added …); completing one can move the claim forward |
| Claim lifecycle | `open → investigating → settled → closed` (+ reopen), one transition table, row-locked |
| Exposures | One per loss item (own vehicle, third-party vehicle/property/injury); money is tracked per exposure |
| Financials | Append-only **ledger** of reserves, payments, recoveries; can't pay more than is reserved |
| Audit history | Every state change, assignment and money movement is logged with who and when |

### Roles (just two)

| Role | Can do |
|---|---|
| **Claimant** (policyholder) | Register, link a policy, report a loss, upload documents, follow status and payments on their own claims |
| **Adjuster** (staff) | See all claims and queues, take/reassign claims, work activities, add exposures, set reserves, issue payments, record recoveries, settle, close, reopen |

Adjusters cannot self-register: `php bin/create-adjuster.php`. The previous *garage* role was removed —
a repair shop is a vendor, not a user of the claims desk; repair quotes are now just exposures,
documents and reserves handled by the adjuster.

## Run it

Requirements: PHP 8.1+ (`pdo_mysql`, `mbstring`, `fileinfo`), MariaDB 10.5+ or MySQL 8.

```bash
# 1. database
mariadb -e "CREATE DATABASE avic_portal;
            CREATE USER 'avic'@'localhost' IDENTIFIED BY 'choose-a-password';
            GRANT ALL ON avic_portal.* TO 'avic'@'localhost';"

# 2. configuration (a .env file in the project root is read automatically)
cp .env.example .env        # then edit AVIC_DB_PASS

# 3. schema + demo data
php bin/migrate.php
php bin/seed.php            # demo accounts, policies and three claims in different stages

# 4. serve (document root is public/)
php -S 127.0.0.1:8080 -t public public/index.php
```

Open <http://127.0.0.1:8080>. Demo password for every seeded account is `Demo2026!`
(override with `AVIC_DEMO_SECRET`):

| Who | Email | Notes |
|---|---|---|
| Claimant | `grace.nakato@example.ug` | has two policies and three claims |
| Claimant | `moses.ssebunya@example.ug` | no policy yet — link `POL-DEMO-0003` with plate `UCC 789Z` |
| Adjuster | `brian.okot@avic.ug` | central, collision specialist (also `sarah.kyomuhendo@`, `david.mugisha@`, `peter.opio@avic.ug`) |

A good walkthrough: sign in as Moses → link the policy → report a loss in the central region →
sign in as Brian → open the new claim → complete *Verify policy coverage* (claim moves to
*Investigating*) → set a reserve → issue a payment larger than the reserve (refused) → settle → close as paid.

## Tests

```bash
mariadb -e "CREATE DATABASE avic_test; GRANT ALL ON avic_test.* TO 'avic'@'localhost';"
export AVIC_TEST_DB_PASS=choose-a-password      # + AVIC_TEST_DB_HOST/PORT/USER/NAME if needed
php bin/test.php            # all suites; php bin/test.php ledger  runs matching tests
php bin/lint.php            # syntax check every PHP file
```

The test database is **wiped before every test** and the runner refuses any database whose name
does not end in `_test`. The suites cover the pure domain rules, the services against a real
database (lifecycle guards, assignment, ledger arithmetic, authorization), and the HTTP layer through
a throwaway PHP server (login lockout, CSRF, access control, the FNOL wizard, uploads).
CI (`.github/workflows/ci.yml`) runs everything on PHP 8.1–8.4 against MariaDB.

## Project layout

```
public/                 web root: index.php (router), assets/css, assets/js
src/Core/               Db, Request, Router, Auth, Csrf, Session, View, Migrator …
src/Domain/             pure rules: Catalog, ClaimLifecycle, CoverageCheck, Rules, Assignment
src/Services/           ClaimService, ActivityService, FinancialService, DocumentService, AuditLog, PolicyGateway
src/Controllers/        thin HTTP handlers: parse input → call a service → render / redirect
templates/              server-rendered pages (PHP templates)
config/rules.php        assignment rules and activity templates, as data
database/migrations/    numbered *.sql files applied by bin/migrate.php
bin/                    migrate, seed, create-adjuster, test, lint
tests/                  domain, service and HTTP tests (no framework needed)
docs/design.md          architecture, data model, state machine, ledger design
```

## Security notes

* Passwords hashed with `password_hash`; sign-in failures are generic, throttled per IP+email (5 in 15 min), and the session id is regenerated on login.
* Every POST requires a CSRF token. Cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS.
* A strict Content-Security-Policy (no inline scripts or styles), `X-Frame-Options: DENY`, `nosniff`.
* All SQL uses prepared statements; all output is escaped.
* Claimants only ever reach claims on their own policies — anything else is a 404, not a 403, so ids can't be probed.
* Uploads: JPEG/PNG/PDF only (checked by content, not file name), 5 MB, random stored names, kept **outside** `public/`
  (`storage/uploads` or `AVIC_UPLOAD_DIR`) and served only through an authorised route.
* `AVIC_ENV=production` refuses an empty DB password and refuses to seed demo data.

See [`docs/design.md`](docs/design.md) for how it fits together and what was deliberately left out.
