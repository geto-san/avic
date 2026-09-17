/* AVIC Portal — mock session + role guard.
   The real portal uses a PHP session; here it lives in per-tab
   sessionStorage so two desks can be open at once. */

(function () {
  const SCRIPT = document.currentScript || [].slice.call(document.scripts).pop();
  const src = SCRIPT.getAttribute('src') || '';
  AVIC.base = src.replace(/assets\/js\/session\.js.*$/, '') || './';
  AVIC.url = path => AVIC.base + path.replace(/^\//, '');
})();

AVIC.KEY = 'avic.session';

/* sessionStorage is blocked when a page is opened off the disk
   (file://). Detect it once so sign-in can say so plainly. */
AVIC.storageOk = (function () {
  try { sessionStorage.setItem('avic.probe', '1'); sessionStorage.removeItem('avic.probe'); return true; }
  catch (e) { return false; }
})();

AVIC.session = function () {
  try { return JSON.parse(sessionStorage.getItem(AVIC.KEY)); }
  catch (e) { return null; }
};

AVIC.signIn = function (userId, opts) {
  const u = AVIC.user(userId);
  if (!u) return null;
  const s = {
    id: u.id, uuid: u.uuid, name: u.full_name, email: u.email,
    role: u.role, status: u.status,
    signedInAt: new Date().toISOString(),
    impersonatedBy: (opts && opts.impersonatedBy) || null
  };
  try { sessionStorage.setItem(AVIC.KEY, JSON.stringify(s)); } catch (e) { /* see AVIC.storageOk */ }
  return s;
};

AVIC.signOut = function () {
  try { sessionStorage.removeItem(AVIC.KEY); sessionStorage.removeItem('avic.draft'); } catch (e) {}
  location.href = AVIC.url('pages/auth/login.html');
};

AVIC.homeFor = role => AVIC.url('pages/' + AVIC.roles[role].dir + '/dashboard.html');

/* ------------------------------------------------------------
   Guard. Each page declares the roles it belongs to:
     <body data-page="queue" data-allow="adjuster">
   No session  → login.  Wrong role → 403, naming both desks.
   Runs before paint so a forbidden page is never visible.
   ------------------------------------------------------------ */
AVIC.guard = function () {
  const body = document.body;
  const allow = (body.dataset.allow || '').split(',').map(s => s.trim()).filter(Boolean);
  const s = AVIC.session();

  if (!allow.length) return s;                 // public page (auth, errors)

  if (!s) {
    try { sessionStorage.setItem('avic.intended', location.pathname + location.search); } catch (e) {}
    location.replace(AVIC.url('pages/auth/login.html?reason=signin'));
    return null;
  }
  if (s.status !== 'active') {
    location.replace(AVIC.url('pages/errors/403.html?reason=status&was=' + s.status));
    return null;
  }
  if (!allow.includes(s.role)) {
    location.replace(AVIC.url('pages/errors/403.html?you=' + s.role + '&need=' + allow.join('|')));
    return null;
  }
  body.dataset.role = s.role;
  return s;
};

/* ------------------------------------------------------------
   Role-scoped navigation. A role's menu is built only from its
   own list — there is no shared "all links" array to leak from.
   ------------------------------------------------------------ */
AVIC.nav = {
  claimant: [
    { group: 'My claims', items: [
      { t: 'Dashboard',    ic: '▤', page: 'dashboard',  href: 'dashboard.html' },
      { t: 'File a claim', ic: '✚', page: 'claim-new',  href: 'claim-new.html' },
      { t: 'My claims',    ic: '▤', page: 'claims',     href: 'claims.html', count: s => AVIC.claimsFor(s).length }
    ]},
    { group: 'Account', items: [
      { t: 'Notifications', ic: '◉', page: 'notifications', href: 'notifications.html', count: s => AVIC.notificationsFor(s).filter(n => !n.is_read).length },
      { t: 'Policies',      ic: '▦', page: 'policies',      href: 'policies.html' },
      { t: 'Profile',       ic: '●', page: 'profile',       href: 'profile.html' }
    ]}
  ],
  adjuster: [
    { group: 'Casework', items: [
      { t: 'Dashboard',     ic: '▤', page: 'dashboard', href: 'dashboard.html' },
      { t: 'Review queue',  ic: '▥', page: 'queue',     href: 'queue.html', count: s => AVIC.claimsFor(s).filter(c => ['submitted','under_review','pending_docs'].includes(c.status)).length },
      { t: 'Decided claims',ic: '✓', page: 'decided',   href: 'decided.html' },
      { t: 'Garage estimates', ic: '▦', page: 'estimates', href: 'estimates.html' }
    ]},
    { group: 'Account', items: [
      { t: 'Notifications', ic: '◉', page: 'notifications', href: 'notifications.html', count: s => AVIC.notificationsFor(s).filter(n => !n.is_read).length },
      { t: 'Profile',       ic: '●', page: 'profile',       href: 'profile.html' }
    ]}
  ],
  garage: [
    { group: 'Workshop', items: [
      { t: 'Dashboard',   ic: '▤', page: 'dashboard',   href: 'dashboard.html' },
      { t: 'Work orders', ic: '▥', page: 'work-orders', href: 'work-orders.html', count: s => AVIC.workOrdersFor(s).filter(w => w.status !== 'closed').length },
      { t: 'My estimates',ic: '▦', page: 'estimates',   href: 'estimates.html' }
    ]},
    { group: 'Account', items: [
      { t: 'Notifications', ic: '◉', page: 'notifications', href: 'notifications.html', count: s => AVIC.notificationsFor(s).filter(n => !n.is_read).length },
      { t: 'Profile',       ic: '●', page: 'profile',       href: 'profile.html' }
    ]}
  ],
  admin: [
    { group: 'Oversight', items: [
      { t: 'Dashboard',  ic: '▤', page: 'dashboard', href: 'dashboard.html' },
      { t: 'All claims', ic: '▥', page: 'claims',    href: 'claims.html' },
      { t: 'Payouts',    ic: '▦', page: 'payouts',   href: 'payouts.html', count: () => AVIC.payouts.filter(p => p.status !== 'completed').length },
      { t: 'Users',      ic: '●', page: 'users',     href: 'users.html', count: () => AVIC.users.filter(u => u.status === 'pending').length }
    ]},
    { group: 'System', items: [
      { t: 'Reports',   ic: '▤', page: 'reports',   href: 'reports.html' },
      { t: 'Audit log', ic: '◈', page: 'audit-log', href: 'audit-log.html' },
      { t: 'Settings',  ic: '⚙', page: 'settings',  href: 'settings.html' },
      { t: 'Notifications', ic: '◉', page: 'notifications', href: 'notifications.html', count: s => AVIC.notificationsFor(s).filter(n => !n.is_read).length }
    ]}
  ]
};
