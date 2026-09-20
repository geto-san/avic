# AVIC — Auto Vehicle Insurance Claims

A claims-handling portal prototype for an insurance company in Uganda. Claimants file
claims against their policies, adjusters review and settle them, and garages quote for
repairs — three desks, one workflow.

**Front end:** static HTML/CSS/JS multi-role shell. All data is fetched from the PHP
API through `assets/js/api.js` and cached in the `AVIC.*` layer; nothing is held in a
hardcoded file anymore.

**Back end:** small PHP API on MySQL that backs authentication, claims, policies,
documents, garage estimates, work orders, notifications, payouts, the audit log and
settings (see `docs/porting-to-php.md` for the mapping).

## Roles

| Role      | Desk                  | Signs up?                |
|-----------|-----------------------|--------------------------|
| Claimant  | File and track claims | Self-service registration |
| Adjuster  | Review queue, settle  | Created internally       |
| Garage    | Quote and repair      | Self-service registration |

There is no administrator role. Claimants and garages register themselves and are
active immediately; adjuster accounts are created by running the seed script.

## What works today

- **Register** (`config/auth/register.php`) — claimant or garage account, validated,
  bcrypt-hashed, status `active` right away. Garage registration captures workshop
  address and trading licence.
- **Login** (`config/auth/login.php`) — password verified against the DB, generic
  errors (never reveals whether an email exists), account-status gate, and a failed-attempt
  lockout (5 failures per IP+email in 15 minutes -> 429, tracked in the `login_attempts`
  table). Success opens a secure PHP session (HTTP-only, SameSite=Lax, Secure over HTTPS)
  and updates `last_login`.
- **Logout** (`config/auth/logout.php`) — destroys the server session.
- **Session bridge** (`assets/js/session.js`) — the server user is mirrored into the
  front end's `sessionStorage`, so the existing role guards and role-specific nav keep
  working; `AVIC.signOut()` clears both.

## Live backend

Claims, policies, documents, garage estimates, work orders, notifications, payouts, the
audit log, and settings are served by real PHP+PDO endpoints under `config/api/` and
persist to MySQL (`avic_portal`). Sign in at `config/auth/login.php` with any demo
account (password `Demo2026!`) to exercise the whole flow for your role:

- Claimants submit claims with declared incidents and document uploads (real files are
  saved to `uploads/` and streamed back through `config/uploads.php`).
- Adjusters see their assignment queue, record approve/reject/request-docs decisions,
  approve or send back garage estimates, and assign garages to open work orders.
- Garages submit (and revise) repair quotes; decisions, notifications and the audit log
  are written to the database.

Documents seeded via `config/db/seed-demo.php` are metadata-only (`file_path = ""`);
they render a placeholder in the viewer until a real upload exists. Uploads are
git-ignored runtime files, never committed.

## Run it

Requirements: PHP 8.x with PDO MySQL, MySQL/MariaDB.

1. Create the database and user (credentials live in `config/db/db_connection.php`, all
   dev defaults):

   ```sql
   CREATE DATABASE IF NOT EXISTS avic_portal;
   CREATE USER IF NOT EXISTS 'avic'@'localhost' IDENTIFIED BY '12345678';
   GRANT ALL PRIVILEGES ON avic_portal.* TO 'avic'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Create the schema. The tables mirror the columns the prototype already uses:
   `users`, `login_attempts`, `claims`, `policies`, `claim_documents`, `garage_estimates`,
   `adjuster_reviews`, `payouts`, `notifications`, `password_resets`, `audit_log`,
   `settings`. `users.role` is the enum `claimant | adjuster | garage`.

   Build it in one command (idempotent — safe to re-run, it skips existing tables):

   ```bash
   php config/db/schema.php
   ```

3. Seed the demo accounts and sample claims (idempotent, upsert-style):

   ```bash
   php config/db/seed-demo.php
   ```

   Sign in with `geto`/`Demo2026!` (claimant), `brian.okot@avic.ug`/`Demo2026!`
   (adjuster) or `desk@kigongomotors.ug`/`Demo2026!` (garage).

4. Serve the folder with PHP's built-in server (executes `.php`; a static server like
   `python3 -m http.server` will return `501` for POST and serve PHP as text):

   ```bash
   php -S 127.0.0.1:8080
   ```

5. Add an adjuster to sign in with (interactive, or pass the password as an argument):

   ```bash
   php config/auth/seed-adjuster.php 'YourPassword123!'
   ```

5. Open http://127.0.0.1:8080 — you'll land on **Sign in**, where you can register a
   claimant or garage account and then sign in.

## Project layout

```
config/
  db/db_connection.php     PDO connection (dev credentials)
  db/schema.php            idempotent schema builder (skips existing tables)
  db/seed-demo.php         demo accounts + sample data (upsert, re-runnable)
  api/                     PHP+PDO JSON endpoints (auth, claims, decisions, estimates,
                           documents, notifications, payouts, audit)
  uploads.php              streaming upload proxy (role-gated, extension whitelisted)
  session.php              secure cookie/session bootstrap
  auth/register.php        sign-up endpoint
  auth/login.php           sign-in endpoint + lockout
  auth/logout.php          destroy session
assets/
  css/avic.css             full stylesheet
  js/api.js                API client (`request` / `post` / `postForm` + upload)
  js/mock-data.js          AVIC.* layer: API responses cached in sessionStorage + role guard
  js/session.js            sessionStorage bridge + role guard + nav
  js/layout.js             rail/topbar shell built from the session role
  js/pages-*.js            per-desk renderers
  js/auth-*.js             register/login page wiring
pages/
  auth/                    sign-in, registration, password screens
  claimant/ adjuster/ garage/   the three desks
  errors/                  403 / 404
docs/
  hardcoded-dependencies.md  pre-port audit of what used to be mock data (historical)
  porting-to-php.md          prototype -> PHP endpoint mapping (current, live)
```

## Security notes

- The front-end `sessionStorage` session is forgeable and is only a UI convenience; the
  PHP session on the server is the real gate. Every future write endpoint must re-check
  `$_SESSION['user']`.
- The JSON-only API is reasonably CSRF-hard (browsers cannot send cross-origin
  `application/json` without a preflight), but add a CSRF token when you start receiving
  real form submissions.
- Swap the dev DB credentials in `config/db/db_connection.php` for environment
  variables on any shared deploy.