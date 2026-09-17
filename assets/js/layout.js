/* AVIC Portal — app shell.
   Rail and topbar are built from AVIC.nav[role] only, so no other
   desk's links are ever rendered. */

AVIC.shell = function (session) {
  if (!session) return;
  const role = AVIC.roles[session.role];
  const dir = AVIC.url('pages/' + role.dir + '/');
  const page = document.body.dataset.page || '';

  /* ---------- rail ---------- */
  const groups = (AVIC.nav[session.role] || []).map(g =>
    '<div class="nav__group"><div class="nav__title">' + UI.esc(g.group) + '</div>' +
    g.items.map(i => {
      const n = typeof i.count === 'function' ? i.count(session) : null;
      return '<a href="' + dir + i.href + '"' + (i.page === page ? ' class="is-active" aria-current="page"' : '') + '>' +
        '<span class="ic" aria-hidden="true">' + i.ic + '</span><span>' + UI.esc(i.t) + '</span>' +
        (n ? '<span class="count">' + n + '</span>' : '') + '</a>';
    }).join('') + '</div>').join('');

  const rail =
    '<aside class="rail">' +
      '<div class="rail__brand">' +
        '<div class="rail__mark">AV</div>' +
        '<div><div class="rail__brandname">AVIC Portal</div>' +
        '<div class="rail__brandsub">Vehicle insurance claims</div></div>' +
      '</div>' +
      '<div class="nameplate">' +
        '<div class="nameplate__role">' + UI.esc(role.desk) + '</div>' +
        '<div class="nameplate__who">' + UI.esc(session.name) + '</div>' +
        '<div class="nameplate__id">' + UI.esc(session.uuid) + '</div>' +
      '</div>' +
      '<nav class="nav" aria-label="' + UI.esc(role.label) + ' navigation">' + groups + '</nav>' +
      '<div class="rail__foot">Prototype build · no live data</div>' +
    '</aside>';

  /* ---------- topbar ---------- */
  const unread = AVIC.notificationsFor(session).filter(n => !n.is_read).length;
  const crumb = document.body.dataset.crumb || '';
  const bar =
    '<header class="topbar">' +
      '<div class="topbar__crumb"><b>' + UI.esc(role.label) + '</b>' + (crumb ? ' · ' + UI.esc(crumb) : '') + '</div>' +
      '<div class="topbar__spacer"></div>' +
      (session.impersonatedBy ? '<span class="tag tag--role">Viewing as this user</span>' : '') +
      '<span class="rolechip"><span class="rolechip__dot"></span><span class="rolechip__t">Signed in as ' + UI.esc(role.label) + '</span></span>' +
      '<button class="iconbtn" id="bell" aria-label="Notifications">◉' +
        (unread ? '<span class="dot">' + unread + '</span>' : '') + '</button>' +
      '<button class="avatar" id="who" aria-label="Account menu">' + UI.initials(session.name) + '</button>' +
    '</header>';

  const shell = document.querySelector('.shell');
  const main = document.querySelector('.main');
  shell.insertAdjacentHTML('afterbegin', rail);
  shell.classList.add('is-ready');
  main.insertAdjacentHTML('afterbegin', bar);

  /* ---------- menus ---------- */
  let open = null;
  function closeMenu() { if (open) { open.remove(); open = null; } }
  function menu(html) {
    closeMenu();
    const el = document.createElement('div');
    el.innerHTML = html;
    open = el.firstElementChild;
    main.appendChild(open);
    setTimeout(() => document.addEventListener('click', onAway), 0);
  }
  function onAway(e) {
    if (open && !open.contains(e.target) && !e.target.closest('#bell,#who')) {
      closeMenu(); document.removeEventListener('click', onAway);
    }
  }

  document.getElementById('bell').onclick = () => {
    if (open && open.dataset.kind === 'bell') return closeMenu();
    const list = AVIC.notificationsFor(session).slice(0, 5);
    menu('<div class="menu" data-kind="bell">' +
      '<div class="menu__head"><h3>Notifications</h3></div>' +
      (list.length ? list.map(n =>
        '<div class="notif' + (n.is_read ? '' : ' is-unread') + '">' +
          '<div class="notif__bar"></div><div>' +
          '<div class="notif__t">' + UI.esc(n.title) + '</div>' +
          '<div class="notif__m">' + UI.esc(n.message) + '</div>' +
          '<div class="notif__d">' + UI.ago(n.created_at) + '</div></div></div>').join('')
        : '<div class="notif"><div class="notif__bar"></div><div class="notif__m">You are all caught up.</div></div>') +
      '<div class="menu__sep"></div>' +
      '<a href="' + dir + 'notifications.html">Open all notifications</a>' +
      '</div>');
  };

  document.getElementById('who').onclick = () => {
    if (open && open.dataset.kind === 'who') return closeMenu();
    menu('<div class="menu menu--sm" data-kind="who">' +
      '<div class="menu__head"><div><h3>' + UI.esc(session.name) + '</h3>' +
      '<div class="tiny muted">' + UI.esc(session.email) + '</div></div></div>' +
      '<a href="' + dir + 'profile.html">Profile</a>' +
      (session.impersonatedBy ? '<button class="menu__item" id="unimpersonate">Return to my admin account</button>' : '') +
      '<div class="menu__sep"></div>' +
      '<button class="menu__item" id="signout">Sign out</button></div>');
    const so = document.getElementById('signout');
    if (so) so.onclick = () => AVIC.signOut();
    const un = document.getElementById('unimpersonate');
    if (un) un.onclick = () => {
      const back = session.impersonatedBy;
      AVIC.signIn(back);
      location.href = AVIC.homeFor(AVIC.user(back).role);
    };
  };

  /* notification polling stand-in — the real portal fetches
     /api/notifications every 30s; here the badge just re-counts. */
  setInterval(() => {
    const b = document.getElementById('bell');
    if (!b) return;
    const n = AVIC.notificationsFor(session).filter(x => !x.is_read).length;
    const dot = b.querySelector('.dot');
    if (n && dot) dot.textContent = n;
    if (!n && dot) dot.remove();
  }, 30000);
};

/* boot every authenticated page */
AVIC.boot = function (render) {
  const s = AVIC.guard();
  if (!s) return;                       // guard is redirecting
  AVIC.shell(s);
  if (render) render(s);
};
