# Porting this prototype to the PHP build

The prototype was written against the dev plan's own vocabulary, so most of the work is
moving logic from the browser to the server rather than rewriting it.

## File-by-file mapping

| Prototype | Becomes |
|---|---|
| `pages/<role>/*.html` | `views/<area>/*.php` rendered into `views/layouts/app.php` |
| `assets/js/layout.js` | `views/partials/sidebar.php` + `views/partials/topbar.php` |
| `AVIC.nav[role]` | the role-aware link list inside `sidebar.php` |
| `AVIC.guard()` | `middleware/AuthMiddleware.php` + `middleware/RoleMiddleware.php` |
| `AVIC.session()` / `signIn` / `signOut` | `core/Session.php` + `core/Auth.php` |
| `AVIC.claimsFor()` etc. | scoped finders on `models/Claim.php` (`forUser`, `forAdjuster`) |
| `AVIC.canSeeClaim()` | an authorisation check called at the top of every claim action |
| `AVIC.garageViewOf()` | a `SELECT` that names only the columns a garage may read |
| `UI.validate` / `data-required` | `core/Validator.php`, with the JS kept as a first pass |
| `UI.dropzone` | `core/FileUploader.php` behind the same drag-drop zone |
| `UI.table` sorting and filters | `core/Paginator.php` plus `ORDER BY` / `WHERE` on the query |
| `data-stub` buttons | the real endpoints (PDF export, CSV, email, password change) |
| `mock-data.js` | `schema.sql` — the arrays already carry the column names |

## Things the prototype fakes, and what replaces them

- **Authentication.** No password is checked. Replace with bcrypt verification, session
  regeneration on login, and the login rate limit from section 10 of the plan.
- **Session storage.** `sessionStorage` becomes a PHP session with an HTTP-only,
  SameSite, HTTPS-only cookie.
- **File uploads.** Files are read with `FileReader` for preview only and never leave the
  browser. Replace with `$_FILES`, real MIME sniffing, UUID renaming, and the
  `public/file.php` streaming proxy so `uploads/` is never reachable by URL.
- **Notification polling.** The badge re-counts on a timer. Point it at
  `GET /api/notifications` instead.
- **State changes.** Approving a claim or releasing a payout mutates an in-memory array
  and is lost on reload. Each becomes a transaction that writes the row, appends to
  `claim_status_history`, writes to `audit_log`, and queues a notification and an email.
- **CSRF.** There are no tokens in the forms. Add the hidden `_csrf_token` field and the
  `CsrfMiddleware` check on every POST.

## Checks the UI shows but the server must own

The adjuster review screen displays three cover checks and refuses an approved amount over
the policy limit, and the payout form does the same. These are conveniences, not controls.
The same three checks — policy active at the incident date, peril covered by the tier,
amount within the remaining cover limit — have to be enforced in `AdjusterController` and
`PayoutController` before anything is written, along with the duplicate-incident check
flagged in section 15 of the plan.

## Suggested order

1. Layout, auth and the role middleware, using the prototype's markup as the view files.
2. Claim wizard and document upload, keeping the client-side steps and validation as-is.
3. Adjuster queue and review, then garage quoting.
4. Admin: users, payouts, audit log, settings.
5. The stubbed exports and emails, then the security pass.
