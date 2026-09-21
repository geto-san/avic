/* ============================================================
   AVIC — app shell
   Builds the left rail and topbar from the signed-in role only.
   ============================================================ */

AVIC.shell = function (session) {
  if (!session) return;
  const role = AVIC.roles[session.role];
  const dir = AVIC.url('pages/' + role.dir + '/');
  const page = document.body.dataset.page || '';

  /* ---------- rail ---------- */
  const groups = (AVIC.nav[session.role] || []).map(g =>
    '<div class="nav__group"><div class="nav__title">' + UI.esc(g.group) + '</div>' + g.items.map(i => {
      const n = typeof i.count === 'function' ? i.count(session) : null;
      return '<a href="' + dir + i.href + '"' + (i.page === page ? ' class="is-active" aria-current="page"' : '') + '>' +
        '<span class="ic" aria-hidden="true">' + i.ic + '</span><span>' + UI.esc(i.t) + '</span>' +
        (n ? '<span class="count">' + n + '</span>' : '') + '</a>';
    }).join('')).join('');

  const rail = '<aside class="rail">' +
    '<div class="rail__brand"><div class="rail__mark">AV</div>' +
    '<div><div class="rail__brandname">AVIC</div>' +
    '<div class="rail__brandsub">Vehicle insurance claims</div></div></div>' +
    '<nav class="nav" aria-label="' + UI.esc(role.label) + ' navigation">' + groups + '</nav>' +
    '<div class="rail__foot">Live data · served from the API</div>' +
    '</aside>';

  /* ---------- topbar ---------- */
  const bar =
    '<header class="topbar">' +
      '<div class="topbar__spacer"></div>' +
      '<span class="topbar__who" title="' + UI.esc(session.email) + '">' + UI.esc(session.name) + '</span>' +
      '<button class="btn btn--ghost btn--sm" id="signout" type="button">Sign out</button>' +
    '</header>';

  const shell = document.querySelector('.shell');
  const main = document.querySelector('.main');
  shell.insertAdjacentHTML('afterbegin', rail);
  shell.classList.add('is-ready');
  main.insertAdjacentHTML('afterbegin', bar);

  /* ---------- sign out ---------- */
  const so = document.getElementById('signout');
  if (so) so.onclick = () => AVIC.signOut();
};

/* boot every authenticated page: guard locally, then ask the server for
   this session's data (which also confirms the session is still real). */
AVIC.boot = function (render) {
  const s = AVIC.guard();
  if (!s) return;                       // guard is redirecting
  AVIC.loadData().then(fresh => {
    AVIC.shell(fresh || AVIC.session());
    if (render) render(fresh || AVIC.session());
  }).catch(() => { /* loadData has already redirected */ });
};

/* refresh the data behind the current page (after API writes) */
AVIC.rerender = function (render) {
  AVIC.refresh().then(fresh => {
    if (render) render(fresh || AVIC.session());
  });
};
