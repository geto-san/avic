/* ============================================================
   AVIC — API client + data hydration
   The single talking-to-server module. AVIC.loadData() pulls the
   role-scoped dataset for the signed-in user and pours it into the
   AVIC.* arrays every page already reads, so the renderers needed no
   rewrite; writes go back through API.post() and the arrays refresh.
   The server session is the real gate — sessionStorage is only a cache.
   ============================================================ */

window.API = (typeof API !== 'undefined') ? API : {};

API.base = (typeof AVIC !== 'undefined' && AVIC.url) ? AVIC.url : f => f;

API.request = async function (path, opts) {
  const init = opts || {};
  init.credentials = 'same-origin';
  init.headers = { ...init.headers };

  let res;
  try {
    res = await fetch(API.base(path), init);
  } catch (err) {
    return { status: 0, ok: false, data: null };
  }

  let data = {};
  try { data = await res.json(); } catch (e) { /* empty body */ }

  /* the server session is gone or the account changed — the local
     mirror is worthless, send the person back to sign-in. */
  if (res.status === 401) {
    try { sessionStorage.removeItem(AVIC.KEY); } catch (e) {}
    location.replace(API.base('pages/auth/login.html?reason=signin'));
    return { status: 401, ok: false, data };
  }
  return { status: res.status, ok: res.ok, data };
};

API.get = function (path) {
  return API.request(path, { method: 'GET' });
};

API.post = function (path, payload) {
  return API.request(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
};

API.postForm = function (path, formData) {
  return API.request(path, { method: 'POST', body: formData });
};

/* ------------------------------------------------------------
   Hydration. Fills the AVIC.* arrays from /config/api/bootstrap.php.
   Resolves with the current session object, or redirects away when
   the server does not recognise this browser.
   ------------------------------------------------------------ */
AVIC.hydrate = function (d) {
  ['users', 'policies', 'claims', 'documents', 'estimates', 'workOrders',
   'notifications', 'payouts', 'garages'].forEach(k => { AVIC[k] = d[k] || []; });
  if (d.settings) Object.assign(AVIC.settings, d.settings);

  if (d.me) {
    const prev = AVIC.session() || {};
    AVIC.startServerSession(d.me, { impersonatedBy: prev.impersonatedBy || null });
  }
  return AVIC.session();
};

AVIC.loadData = async function () {
  const res = await API.get('config/api/bootstrap.php');

  if (res.status === 401) {
    try { sessionStorage.removeItem(AVIC.KEY); } catch (e) {}
    location.replace(API.base('pages/auth/login.html?reason=signin'));
    throw new Error('unauthenticated');
  }
  if (!res.ok || !res.data) {
    location.replace(API.base('pages/auth/login.html?reason=server'));
    throw new Error('bootstrap failed');
  }
  if (res.data.me && res.data.me.status && res.data.me.status !== 'active') {
    location.replace(API.base('pages/errors/403.html?reason=status&was=' + res.data.me.status));
    throw new Error('inactive account');
  }

  return AVIC.hydrate(res.data);
};

/* Refresh the data after a write (claim, decision, quote, …). Pages call
   this then re-render. */
AVIC.refresh = function () {
  return AVIC.loadData();
};