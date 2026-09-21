# Prototype → PHP endpoint mapping

The original plan sketched a full MVC rewrite (`views/`, `middleware/`, `models/`,
`core/`). That turned out to be more than the app needed: the static HTML/CSS/JS shell
already separates markup from logic cleanly, so the port kept it and replaced only the
data layer — the front end still renders the same pages, now filled from real JSON
endpoints instead of a hardcoded array.

## What each prototype concept became

| Prototype concept | Lives now in |
|---|---|
| `AVIC.nav[role]`, rail/topbar | `assets/js/layout.js` (unchanged — still client-side, still role-scoped) |
| `AVIC.guard()` | still `assets/js/session.js` for instant UI redirects, backed by `apiUser()` in `config/api/_helpers.php` for the real, unspoofable check on every write |
| `AVIC.session()` / `startServerSession` / `signOut` | mirrors the PHP session (`config/session.php`) into `sessionStorage`; the PHP session is the actual gate |
| Auth (login/register/forgot/reset) | `config/auth/*.php`, sharing `config/auth/_helpers.php` |
| `AVIC.claimsFor()` / `canSeeClaim()` / `garageViewOf()` | role-scoped SQL in `config/api/bootstrap.php`, `config/api/_helpers.php::apiCanSeeClaim()` |
| `UI.validate` / `data-required` | still a client-side first pass; every endpoint under `config/api/` re-validates and re-authorizes server-side |
| `UI.dropzone` (preview only) | real uploads via `config/api/_helpers.php::saveUploads()`, streamed back through `config/uploads.php` |
| `UI.table` sorting/filters | still client-side over the rows `bootstrap.php` already scoped to the signed-in user |
| `data-stub` buttons | removed; every one now calls a real endpoint (see table below) |
| `mock-data.js` arrays | `config/db/schema.php` (tables) + `config/db/seed-demo.php` (demo rows) |

## Endpoints

| Endpoint | Does |
|---|---|
| `config/api/bootstrap.php` | GET — hydrates the front end's `AVIC.*` arrays for the signed-in role |
| `config/api/claims.php` | save/submit a claim (with file uploads) |
| `config/api/claims-assign.php` | adjuster assigns a garage to a claim |
| `config/api/claims-decision.php` | adjuster approves/rejects/requests documents |
| `config/api/claims-pdf.php` | claim summary as a downloadable document |
| `config/api/documents.php` | mark a document verified |
| `config/api/estimates.php` | garage submits/revises a repair quote |
| `config/api/estimates-decision.php` | adjuster approves or sends back a quote |
| `config/api/notifications.php` | mark one/all notifications read |
| `config/api/payouts-update.php` | update a payout's status |
| `config/uploads.php` | role-gated streaming proxy for uploaded files |

## Still worth doing

- **CSRF tokens** once the API takes real `<form>` submissions instead of JSON-only
  `fetch()` calls (see the Security notes in the README for why JSON-only is
  reasonably CSRF-hard today).
- **Server-side cover checks** (policy active at the incident date, peril covered by
  the tier, amount within the remaining limit) are shown in the UI as guidance; make
  sure `claims-decision.php` and `payouts-update.php` enforce them before writing,
  alongside a duplicate-incident check.
