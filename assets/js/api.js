// HTTP requests with session cookies and CSRF protection.

window.API = (typeof API !== 'undefined') ? API : {};

API.base = (typeof AVIC !== 'undefined' && AVIC.url) ? AVIC.url : f => f;

API.request = async function (path, opts) {
  // Include the session cookie so PHP can identify who made this request.
  const init = opts || {};
  init.credentials = 'same-origin';
  if (!init.signal && typeof AbortSignal !== 'undefined' && AbortSignal.timeout) init.signal = AbortSignal.timeout(20000);
  init.headers = { ...init.headers };

  let res;
  try {
    if (!['GET', 'HEAD'].includes(init.method || 'GET')) {
      init.headers['X-CSRF-Token'] = await AVIC.csrfToken();
    }
    res = await fetch(API.base(path), init);
  } catch (err) {
    return { status: 0, ok: false, data: null };
  }

  let data = {};
  try { data = await res.json(); } catch (e) {  }

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
  // The browser sets multipart/form-data and its boundary when files are sent.
  return API.request(path, { method: 'POST', body: formData });
};

/* ------------------------------------------------------------
   Hydration. Fills the AVIC.* arrays from /config/api/bootstrap.php.
   Resolves with the current session object, or redirects away when
   the server does not recognise this browser.
   ------------------------------------------------------------ */
AVIC.hydrate = function (d) {
  ['users', 'policies', 'claims', 'documents', 'estimates', 'workOrders',
   'notifications', 'payouts', 'garages', 'history', 'documentRequests', 'requestedDocuments', 'settlements', 'paymentEvidence'].forEach(k => { AVIC[k] = d[k] || []; });
  if (d.settings) Object.assign(AVIC.settings, d.settings);

  if (d.me) {
    AVIC.startServerSession(d.me);
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
    throw new Error('bootstrap failed');
  }
  const meStatus = res.data.me?.status;
  if (meStatus && meStatus !== 'active') {
    location.replace(API.base('pages/errors/403.html?reason=status&was=' + meStatus));
    throw new Error('inactive account');
  }

  return AVIC.hydrate(res.data);
};

/* Call after a successful write to fetch current database values. Posting
   alone does not update AVIC's arrays; callers refresh or navigate to a page. */
AVIC.refresh = function () {
  return AVIC.loadData();
};
