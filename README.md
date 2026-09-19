# AVIC — Auto Vehicle Insurance Claims

A claims-handling portal prototype for an insurance company in Uganda. Claimants file
claims against their policies, adjusters review and settle them, and garages quote for
repairs — three desks, one workflow.

**Front end:** static HTML/CSS/JS prototype (role-based shell, mock data behind a
single `AVIC.*` data layer).

**Back end:** small PHP API on MySQL that backs authentication. Registration, login and
logout are real; the rest of the portal still renders from in-browser mock data while it
awaits the port to live endpoints (see `docs/hardcoded-dependencies.md`).

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

## Not yet dynamic (prototype)

Everything beyond auth renders from `assets/js/mock-data.js` and mutates in-browser
arrays. Claims are never sent to a server, documents are preview-only, decisions and
estimates are lost on reload, and the notification/audit/payout data is fabricated.
A file-by-file account of what must move to the database and endpoints lives in
`docs/hardcoded-dependencies.md`.

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

   A schema builder is not yet committed — bring up the tables with one command per table
   from the existing `mock-data.js` column names, or share the `SHOW CREATE TABLE`
   exports if you have them locally.

3. Serve the folder with PHP's built-in server (executes `.php`; a static server like
   `python3 -m http.server` will return `501` for POST and serve PHP as text):

   ```bash
   php -S 127.0.0.1:8080
   ```

4. Add an adjuster to sign in with (interactive, or pass the password as an argument):

   ```bash
   php config/auth/seed-adjuster.php 'YourPassword123!'
   ```

5. Open http://127.0.0.1:8080 — you'll land on **Sign in**, where you can register a
   claimant or garage account and then sign in.

## Project layout

```
config/
  db/db_connection.php     PDO connection (dev credentials)
  session.php              secure cookie/session bootstrap
  auth/register.php        sign-up endpoint
  auth/login.php           sign-in endpoint + lockout
  auth/logout.php          destroy session
  auth/seed-adjuster.php   create an adjuster account from the CLI
assets/
  css/avic.css             full stylesheet
  js/mock-data.js          AVIC.users/claims/policies/... (the mock data layer)
  js/session.js            sessionStorage bridge + role guard + nav
  js/layout.js             rail/topbar shell built from the session role
  js/pages-*.js            per-desk renderers
  js/auth-*.js             register/login page wiring
pages/
  auth/                    sign-in, registration, password screens
  claimant/ adjuster/ garage/   the three desks
  errors/                  403 / 404
docs/
  avic-dev-plan.html       original design plan
  porting-to-php.md        prototype -> PHP mapping notes
  hardcoded-dependencies.md  everything still waiting on a real backend
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