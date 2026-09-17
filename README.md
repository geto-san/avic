# AVIC Portal — UI prototype

A front-end-only build of the Automated Vehicle Insurance Claims portal described in
`avic-dev-plan.html`. Every screen, interaction and state is here; none of it talks to a
server. There is no PHP, no database and no file upload — the mock data in
`assets/js/mock-data.js` stands in for the twelve MariaDB tables, using the same table and
column names so the port to vanilla PHP is a transcription rather than a redesign.

## Running it

The prototype keeps its session in `sessionStorage`, which browsers block for pages opened
straight off the disk. Serve the folder instead:

```bash
cd avic-portal-ui
python3 -m http.server 8080
# then open http://localhost:8080
```

Or drop the folder into XAMPP's `htdocs` and open `http://localhost/avic-portal-ui/`.
Opening `index.html` by double-clicking will show a notice explaining this.

## Signing in

Any password works. The sign-in screen lists four demo desks; pick one, or type one of
these emails:

| Desk | Email | Who |
|---|---|---|
| Claimant | `doreen@example.ug` | Ahumuza Doreen — four claims, one draft, one paid |
| Adjuster | `brian.okot@avic.ug` | Okot Brian — five claims assigned, one past its deadline |
| Garage | `desk@kigongomotors.ug` | Kigongo Motors — three work orders |
| Administrator | `sylvia@avic.ug` | Nakato Sylvia — the whole book |

Sessions are per browser tab, so you can open two tabs and sit at two desks at once without
them interfering.

Other accounts in the data exercise the edge cases: `pride.n@example.ug` is pending
approval and `r.kato@example.ug` is suspended — both are refused at sign-in with an
explanation.

## How the four desks are kept apart

This was the main design constraint, so it is enforced in four places rather than one.

1. **Separate route space.** Each role owns a folder under `pages/`. Every page declares
   `data-allow` in its `<body>` tag, and `AVIC.guard()` in `session.js` runs before the
   page paints: no session sends you to sign-in, the wrong role sends you to a 403 that
   names both desks, and a suspended account is stopped outright.
2. **Separate code.** A role's rendering logic lives in its own file
   (`pages-claimant.js`, `pages-adjuster.js`, `pages-garage.js`, `pages-admin.js`) and only
   that file is loaded on that role's pages. The navigation is built from
   `AVIC.nav[role]` — there is no combined link list to leak from.
3. **Scoped reads.** No page touches `AVIC.claims` directly. Everything goes through
   `AVIC.claimsFor(session)`, `AVIC.workOrdersFor(session)` or
   `AVIC.notificationsFor(session)`, and a single `AVIC.canSeeClaim()` implements the role
   matrix from section 06 of the plan. Opening a claim id you have no right to lands on
   the 403 screen.
4. **Reduced projections.** A garage never receives a whole claim. `AVIC.garageViewOf()`
   hands back the vehicle, plate, damage type and description only — no claimant identity,
   policy number or settlement figure. That projection is what the quoting screen renders.

Each role also carries its own accent colour through the rail, nameplate and chips
(claimant teal, adjuster indigo, garage orange, administrator crimson), so which desk you
are at is legible at a glance rather than something you have to remember.

One caveat that matters for the real build: because this is a browser-only prototype, all
the mock data ships to every client. Isolation here demonstrates the interaction design.
In the PHP build the same rules have to live in the controllers and models — a hidden link
is not access control.

## What's in the box

```
avic-portal-ui/
├── index.html                 routes to your desk, or to sign-in
├── pages/
│   ├── auth/                  login, register, forgot-password, reset-password
│   ├── claimant/              dashboard, claims, claim-new (wizard), claim-detail,
│   │                          policies, notifications, profile
│   ├── adjuster/              dashboard, queue, review, decided, estimates,
│   │                          notifications, profile
│   ├── garage/                dashboard, work-orders, estimate-new, estimates,
│   │                          notifications, profile
│   ├── admin/                 dashboard, claims, claim-detail, users, payouts,
│   │                          reports, audit-log, settings, notifications, profile
│   └── errors/                403, 404
├── assets/
│   ├── css/avic.css           one stylesheet, light theme, print rules included
│   └── js/
│       ├── mock-data.js       the twelve tables as arrays, plus scoped readers
│       ├── session.js         mock session, role guard, per-role navigation
│       ├── ui.js              formatting, toasts, modals, tables, dropzone, charts
│       ├── layout.js          rail and topbar, built from the session's role
│       ├── pages-*.js         one file per desk
│       ├── pages-common.js    notifications and profile, shared
│       ├── auth.js            sign-in, registration, password reset
│       └── errors.js          the 403 and 404 screens
└── docs/porting-to-php.md     how each piece maps onto the dev plan
```

## Interactions that actually work

- Five-step claim wizard with per-step validation, a running estimate total, a draft
  auto-save tick and a draft restore
- Drag-and-drop file zone with image thumbnails, size and type rejection, progress bars
- Sortable, searchable, filterable tables everywhere
- Adjuster review: document viewer with thumbnail strip, mark-as-verified, cover checks
  against the policy, decision form that refuses an amount above the cover limit
- Garage quoting with adjuster revision notes fed back
- Admin user management: approve, suspend, restore, and "view as" a claimant with a
  banner and a one-click return to the admin session
- Payout release with confirmation, notification bell and panel, toasts, confirm dialogs,
  keyboard focus, reduced-motion support, responsive down to a phone

Anything that would need a server is a no-op that says so: buttons marked `data-stub`
raise a toast reading "prototype only, nothing was saved."

## Future prospects

Deliberately left as tagged placeholder panels rather than half-built features: OCR on
police reports, map pin for incident location, fraud signals, automatic adjuster workload
balancing, repair tracking, parts ordering and job cards, photo markup, two-step sign-in,
SMS via Africa's Talking, bank and mobile-money integration, built reports with date
ranges, retention and privacy controls, granular permissions, claim messaging, PDF summary
and receipt generation.

## Tested

All 36 pages were rendered headlessly under each role with no JavaScript errors, and the
access matrix was checked case by case (a claimant refused another claimant's claim, an
adjuster refused a colleague's claim, a garage refused a claim it holds no work order for,
an administrator allowed everywhere).
