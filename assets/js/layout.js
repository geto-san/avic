// Shared sidebar, topbar, page startup and refresh.

AVIC.shell = function (session) {
  if (!session) return;
  const role = AVIC.roles[session.role];
  const dir = AVIC.url('pages/' + role.dir + '/');
  const page = document.body.dataset.page || '';

  const groups = (AVIC.nav[session.role] || []).map(g =>
    '<div class="nav__group">' + g.items.map(i => {
      const n = typeof i.count === 'function' ? i.count(session) : null;
      return '<a href="' + dir + i.href + '"' + (i.page === page ? ' class="is-active" aria-current="page"' : '') + '>' +
        '<span class="ic" aria-hidden="true">' + i.ic + '</span><span>' + UI.esc(i.t) + '</span>' +
        (n ? '<span class="count">' + n + '</span>' : '') + '</a>';
    }).join('')).join('');

  const rail = '<aside class="rail is-collapsed" id="navigation">' +
    '<div class="rail__brand"><div class="rail__mark">AV</div>' +
    '<div><div class="rail__brandname">AVIC</div>' +
    '</div></div>' +
    '<nav class="nav" aria-label="' + UI.esc(role.label) + ' navigation">' + groups + '</nav>' +
    '<div class="rail__foot"><button class="btn btn--block" id="signout" type="button">Sign out</button></div>' +
    '</aside>';

  const bar =
    '<header class="topbar">' +
      '<button class="btn nav-toggle" type="button" aria-controls="navigation" aria-expanded="false">Menu</button>' +
      '<h1 class="topbar__title">' + UI.esc(document.body.dataset.title || 'Dashboard') + '</h1>' +
    '</header>';

  const shell = document.querySelector('.shell');
  const main = document.querySelector('.main');
  shell.insertAdjacentHTML('afterbegin', rail);
  shell.classList.add('is-ready');
  main.insertAdjacentHTML('afterbegin', bar);
  const content = main.querySelector('main');
  content.id = 'main-content';
  content.tabIndex = -1;
  shell.insertAdjacentHTML('beforebegin', '<a class="skip-link" href="#main-content">Skip to content</a>');
  main.querySelector('.nav-toggle').onclick = event => {
    const collapsed = shell.querySelector('.rail').classList.toggle('is-collapsed');
    event.currentTarget.setAttribute('aria-expanded', String(!collapsed));
    if (!collapsed) shell.querySelector('.nav a[aria-current], .nav a')?.focus();
  };
  shell.querySelector('.rail').addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    shell.querySelector('.rail').classList.add('is-collapsed');
    main.querySelector('.nav-toggle').setAttribute('aria-expanded', 'false');
    main.querySelector('.nav-toggle').focus();
  });
  const back = {review:['queue.html','Back to review queue'], 'claim-detail':['claims.html','Back to my claims'], 'estimate-new':['work-orders.html','Back to work orders']}[page];
  if (back) content.insertAdjacentHTML('afterbegin', '<a class="page-back" href="' + back[0] + '">' + back[1] + '</a>');
  content.querySelectorAll('.filters').forEach(filters => {
    const fields = [...filters.querySelectorAll('input[type=search],select')];
    if (!fields.length) return;
    const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'btn'; clear.textContent = 'Clear filters';
    clear.onclick = () => fields.forEach(field => { field.value = ''; field.dispatchEvent(new Event(field.tagName === 'SELECT' ? 'change' : 'input', {bubbles:true})); });
    filters.insertBefore(clear, filters.querySelector('.page__actions'));
  });

  const so = document.getElementById('signout');
  if (so) so.onclick = () => AVIC.signOut();
};

/* boot every authenticated page: guard locally, then ask the server for
   this session's data (which also confirms the session is still real). */
AVIC.boot = function (render) {
  // Page startup: local navigation check -> server data -> menu -> page content.
  const s = AVIC.guard();
  if (!s) return;                       // guard is redirecting
  const loading = document.createElement('div');
  loading.className = 'workspace-loading'; loading.setAttribute('role', 'status');
  loading.textContent = 'Loading your workspace…'; document.body.prepend(loading);
  AVIC.loadData().then(async fresh => {
    AVIC.shell(fresh || AVIC.session());
    if (render) await render(fresh || AVIC.session());
    loading.remove();
  }).catch(() => {
    loading.innerHTML = '<p>Could not load the workspace. Check your connection and try again.</p><button class="btn" type="button">Try again</button>';
    loading.querySelector('button').onclick = () => location.reload();
  });
};

AVIC.rerender = function (render) {
  // After a write, read the server again before rebuilding the page content.
  AVIC.refresh().then(fresh => {
    if (render) render(fresh || AVIC.session());
  }).catch(() => UI.toast('Could not refresh the page. Your current entries have been kept.', 'bad'));
};
