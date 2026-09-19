# Hardcoded data waiting on a real backend

Everything below is currently served from `assets/js/mock-data.js` in the browser and
mutated in memory. A page reload or a new visitor discards it all. This file is the
porting checklist: for each item, replace the in-memory read/write with a stored DB query
and a JSON endpoint behind the existing PHP layer. File:line references are current as of
the last commit.

## 1. Data sets (read from memory)

All live in `assets/js/mock-data.js`. Columns mirror the `avic_portal` schema already
present in the DB; the tables exist but hold no seed data except `users` and
`login_attempts`.

| Dataset        | Holds                                    | Consumers (examples)                        | Must become (table)      |
|----------------|------------------------------------------|---------------------------------------------|--------------------------|
| `AVIC.users`   | 8 demo users, id 1-9, the only identity source for profile-ish renders | `user()`, nameplates now removed; `pages-adjuster`, claim detail | `users` (already real — front end should hydrate from PHP session + API instead of this array) |
| `AVIC.policies`| 4 policies                               | claimant `policies`, wizard policy picker    | `policies`               |
| `AVIC.claims`  | claims 101-107 with statuses, amounts, assignees | claimant/adjuster lists, detail, review, queues | `claims` + `claim_status_history` |
| `AVIC.documents`| fake doc rows (photo/report types)      | adjuster review *Documents verified* panel, claimant detail | `claim_documents`        |
| `AVIC.estimates`| garage quotes, status journey            | garage `My estimates`, adjuster `Garage estimates` | `garage_estimates`       |
| `AVIC.workOrders`| garage assignments                      | garage dashboard, `claimsFor(garage)`, nav badges | `claims.garage_id` + assignment audit |
| `AVIC.notifications`| per-user inbox, `is_read` flags       | bell menu, notifications pages, nav badges  | `notifications`          |
| `AVIC.payouts` | settlement rows, `approved_by` now points at an adjuster | claimant claim detail payout panel           | `payouts`                |
| `AVIC.settings`| SLA days, max upload MB, currency, support email | wizard SLA text, `UI.dropzone` size limit    | `settings`               |
| `AVIC.labels`  | status/claim-type/doc/coverage vocabulary | badges and pickers everywhere               | DB enum text or a static map (fine to keep client-side) |

## 2. Writes performed in memory (lost on reload)

| Location                                   | Action                                            | Needs endpoint + table                                  |
|--------------------------------------------|---------------------------------------------------|---------------------------------------------------------|
| `pages-claimant.js` `Claimant.wizard` submit | "Claim submitted in the prototype — nothing was sent" | `POST /api/claims` → insert into `claims` + status history |
| `pages-claimant.js` claim wizard            | `sessionStorage 'avic.draft'` autosave/restore    | `POST /api/drafts` (or keep client-side; the plan wants server draft) |
| `pages-claimant.js` claim detail            | `data-stub="Claim summary PDF"` download          | `GET /api/claims/{id}/pdf`                              |
| `pages-adjuster.js` decision form           | sets `c.status` in memory (approve/reject/request-docs) | `POST /api/claims/{id}/decision` → status history + notification + email |
| `pages-adjuster.js` estimates               | `e.status = 'approved'` / `'rejected'`, `e.adjuster_notes` in memory | `POST /api/estimates/{id}/decision`                     |
| `pages-adjuster.js` review                  | receive `docs`/`est` panels, all read-only         | served by the claim GET                                |
| `pages-garage.js` estimate form submit      | "Quote sent in the prototype — nothing was saved"  | `POST /api/estimates` (create/update for that work order) |
| `pages-garage.js` dropzone                  | file list is preview-only (`UI.dropzone`)          | `POST /api/claims/{id}/documents` w/ real upload, MIME sniff, UUID rename; serve via streaming proxy (see `docs/porting-to-php.md`) |
| `pages-common.js` notifications             | `n.is_read = 1`, `mark-all` sets all read          | `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all` |
| `review.html` "Assign a garage"             | `data-stub="Assign a garage"` toast                | `POST /api/claims/{id}/assign-garage`                  |

## 3. Security gaps to close while porting

- **Forgeable front-end session.** `sessionStorage['avic.session']` can be written by
  anyone. The server PHP session is the gate; every endpoint must authorize against
  `$_SESSION['user']` (role + status + ownership), never against the client payload.
- **Guard is client-side.** `AVIC.guard()` just redirects in-browser; protected pages
  are served to anyone who can produce a session blob. Real role checks belong in a
  PHP middleware layer once views move server-side.
- **Mock users have no real accounts.** A claimant/adjuster signed in with a real DB
  account still sees *demo* claims because lookups use `AVIC.users`/`AVIC.claims` keyed
  by mock ids. Hydrate the front end from the real session user and real claim queries.
- **Documents are not really uploaded or secured.** File list stays in browser memory;
  no server storage, no authorization on retrieval.
- **CSRF/replay.** JSON-only endpoints are okay-ish, but add tokens when forms become
  real; also add `last_login`/session-fixation handling per endpoint.

## 4. Suggested order

1. Seed the real tables (`policies`, `claims`, `garage_estimates`, …) with the current
   mock rows so the UI keeps working during the port.
2. Replace `AVIC.user(…)` and claims lookups with real queries keyed off the PHP session
   user.
3. Claims CRUD + documents upload.
4. Adjuster decisions + notifications + status history.
5. Garage estimates + assignment.
6. Payouts (starts from an adjuster-approved claim).