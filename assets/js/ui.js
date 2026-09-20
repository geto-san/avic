/* ============================================================
   AVIC — shared UI helpers
   Formatting, badges, toasts, confirm dialogs, sortable tables,
   character counters, drag-and-drop preview, tiny bar charts.
   ============================================================ */

const UI = {};

/* ---------- escaping & formatting ---------- */
UI.esc = v => String(v == null ? '' : v)
  .replaceAll('&', '&').replaceAll('<', '<')
  .replaceAll('>', '>').replaceAll('"', '"');

UI.money = function (n, withUnit) {
  if (n == null || n === '') return '—';
  const s = Number(n).toLocaleString('en-UG', { maximumFractionDigits: 0 });
  return withUnit === false ? s : '<span class="unit">UGX</span>' + s;
};
UI.moneyPlain = n => n == null ? '—' : 'UGX ' + Number(n).toLocaleString('en-UG', { maximumFractionDigits: 0 });

UI.date = function (d) {
  if (!d) return '—';
  const dt = new Date(String(d).replace(' ', 'T'));
  if (Number.isNaN(dt.getTime())) return d;
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
UI.dateTime = function (d) {
  if (!d) return '—';
  const dt = new Date(String(d).replace(' ', 'T'));
  if (Number.isNaN(dt.getTime())) return d;
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ', ' +
         dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};
UI.ago = function (d) {
  if (!d) return '';
  const dt = new Date(String(d).replace(' ', 'T'));
  const mins = Math.round((Date.now() - dt) / 60000);
  if (Number.isNaN(mins)) return '';
  if (mins < 60) return mins + ' min ago';
  if (mins < 1440) return Math.round(mins / 60) + ' h ago';
  const days = Math.round(mins / 1440);
  return days < 30 ? days + ' d ago' : UI.date(d);
};
UI.size = b => b == null ? '—' : (b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB');

UI.badge = function (status, dict) {
  const map = dict || AVIC.labels.status;
  return '<span class="badge badge--' + UI.esc(status) + '">' + UI.esc(map[status] || status) + '</span>';
};

/* days remaining against the 14-day SLA */
UI.sla = function (due) {
  if (!due) return '<span class="muted">—</span>';
  const days = Math.ceil((new Date(due) - new Date()) / 86400000);
  if (days < 0)  return '<span class="sla sla--late">' + Math.abs(days) + ' d over</span>';
  if (days <= 3) return '<span class="sla sla--warn">' + days + ' d left</span>';
  return '<span class="sla sla--ok">' + days + ' d left</span>';
};

UI.initials = name => (name || '?').split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();

UI.qs = key => new URLSearchParams(location.search).get(key);

/* ---------- toasts ---------- */
UI.toast = function (msg, kind) {
  let box = document.querySelector('.toasts');
  if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
  const el = document.createElement('div');
  el.className = 'toast' + (kind ? ' toast--' + kind : '');
  el.setAttribute('role', 'status');
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(() => el.remove(), 3800);
};

/* ---------- modal / confirm ---------- */
UI.modal = function (opts) {
  const wrap = document.createElement('div');
  wrap.className = 'overlay';
  wrap.innerHTML =
    '<div class="modal" role="dialog" aria-modal="true">' +
      '<div class="modal__head"><h2>' + UI.esc(opts.title) + '</h2></div>' +
      '<div class="modal__body">' + (opts.body || '') + '</div>' +
      '<div class="modal__foot">' +
        '<button class="btn" data-x>' + UI.esc(opts.cancel || 'Cancel') + '</button>' +
        '<button class="btn ' + (opts.danger ? 'btn--danger' : 'btn--primary') + '" data-ok>' + UI.esc(opts.confirm || 'Confirm') + '</button>' +
      '</div>' +
    '</div>';
  document.body.appendChild(wrap);
  const close = () => wrap.remove();
  wrap.querySelector('[data-x]').onclick = close;
  wrap.addEventListener('click', e => { if (e.target === wrap) close(); });
  document.addEventListener('keydown', function esc(e) {
    if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
  });
  wrap.querySelector('[data-ok]').onclick = () => {
    const form = wrap.querySelector('form');
    const data = form ? Object.fromEntries(new FormData(form).entries()) : {};
    close();
    if (opts.onConfirm) opts.onConfirm(data);
  };
  const first = wrap.querySelector('input, select, textarea, [data-ok]');
  if (first) first.focus();
  return wrap;
};

UI.confirm = (title, body, onYes, label) =>
  UI.modal({ title, body: '<p>' + body + '</p>', confirm: label || 'Confirm', danger: true, onConfirm: onYes });

/* prototype stub — used by every control that would need a server */
UI.stub = function (what) {
  UI.toast(what + ' — prototype only, nothing was saved.', 'ok');
};

/* ---------- table: render, sort, filter ---------- */
UI.table = function (target, cfg) {
  const host = typeof target === 'string' ? document.querySelector(target) : target;
  if (!host) return;
  let rows = cfg.rows.slice();
  let sortKey = cfg.sortKey || null, sortDir = cfg.sortDir || 'desc';

  function draw() {
    let view = rows.slice();
    if (cfg.filter) view = view.filter(cfg.filter);
    if (sortKey) {
      const col = cfg.cols.find(c => c.key === sortKey) || {};
      view.sort((a, b) => {
        const av = col.sortValue ? col.sortValue(a) : a[sortKey];
        const bv = col.sortValue ? col.sortValue(b) : b[sortKey];
        if (av == null) return 1; if (bv == null) return -1;
        return (av > bv ? 1 : av < bv ? -1 : 0) * (sortDir === 'asc' ? 1 : -1);
      });
    }
    if (!view.length) {
      host.innerHTML = '<div class="empty"><div class="empty__t">' + UI.esc(cfg.emptyTitle || 'Nothing here yet') + '</div>' +
        '<div class="empty__d">' + (cfg.emptyBody || '') + '</div>' + (cfg.emptyAction || '') + '</div>';
      return;
    }
    const head = cfg.cols.map(c =>
      '<th' + (c.sortable ? ' class="sortable" data-k="' + c.key + '"' : '') + (c.width ? ' style="width:' + c.width + '"' : '') + '>' +
      UI.esc(c.label) + (sortKey === c.key ? ' <span class="arrow">' + (sortDir === 'asc' ? '▲' : '▼') + '</span>' : '') + '</th>').join('');
    const body = view.map(r =>
      '<tr' + (cfg.rowClass ? ' class="' + cfg.rowClass(r) + '"' : '') + '>' +
      cfg.cols.map(c => '<td>' + c.cell(r) + '</td>').join('') + '</tr>').join('');
    host.innerHTML = '<div class="scroll-x"><table class="tbl"><thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table></div>';
    host.querySelectorAll('th.sortable').forEach(th => th.onclick = () => {
      const k = th.dataset.k;
      sortDir = (sortKey === k && sortDir === 'desc') ? 'asc' : 'desc';
      sortKey = k; draw();
    });
    if (cfg.afterDraw) cfg.afterDraw(host, view);
  }

  draw();
  return { redraw: draw, setRows(r) { rows = r; draw(); }, setFilter(f) { cfg.filter = f; draw(); } };
};

/* ---------- character counter ---------- */
UI.counters = function (scope) {
  (scope || document).querySelectorAll('[data-counter]').forEach(el => {
    const max = +el.getAttribute('maxlength') || +el.dataset.counter;
    const out = document.querySelector(el.dataset.counterTarget);
    if (!out) return;
    const tick = () => {
      out.textContent = el.value.length + ' / ' + max;
      out.classList.toggle('is-over', el.value.length > max);
    };
    el.addEventListener('input', tick); tick();
  });
};

/* ---------- drag & drop upload preview (no upload happens) ---------- */
UI.dropzone = function (zoneSel, listSel, onChange) {
  const zone = document.querySelector(zoneSel);
  const list = document.querySelector(listSel);
  if (!zone || !list) return;
  const input = zone.querySelector('input[type=file]');
  const picked = [];

  function render() {
    list.innerHTML = picked.map((f, i) =>
      '<div class="file">' +
        '<div class="file__thumb">' + (f.preview ? '<img alt="" src="' + f.preview + '">' : '<span>PDF</span>') + '</div>' +
        '<div class="file__bar"><span data-bar="' + i + '"></span></div>' +
        '<div class="file__meta">' +
          '<div class="file__n" title="' + UI.esc(f.name) + '">' + UI.esc(f.name) + '</div>' +
          '<div class="file__s">' + UI.size(f.size) + ' · <button class="file__x" data-rm="' + i + '">Remove</button></div>' +
        '</div>' +
      '</div>').join('');
    list.querySelectorAll('[data-rm]').forEach(b => b.onclick = () => {
      picked.splice(+b.dataset.rm, 1); render(); if (onChange) onChange(picked);
    });
    // fake progress so the interaction reads as a real upload
    picked.forEach((f, i) => {
      const bar = list.querySelector('[data-bar="' + i + '"]');
      if (bar) requestAnimationFrame(() => bar.style.width = '100%');
    });
    if (onChange) onChange(picked);
  }

  function accept(files) {
    const maxMB = AVIC.settings.max_upload_mb;
    Array.from(files).forEach( f => {
      if (f.size > maxMB * 1048576) { UI.toast(f.name + ' is over the ' + maxMB + ' MB limit.', 'bad'); return; }
      const ok = /^image\/(jpeg|png|webp)$|^application\/pdf$/.test(f.type);
      if (!ok) { UI.toast(f.name + ' must be a JPEG, PNG, WEBP or PDF.', 'bad'); return; }
      const rec = { file: f, name: f.name, size: f.size, type: f.type, preview: null };
      picked.push(rec);
      if (f.type.startsWith('image/')) {
        const r = new FileReader();
        r.onload = e => { rec.preview = e.target.result; render(); };
        r.readAsDataURL(f);
      }
    });
    render();
  }

  zone.addEventListener('click', () => input && input.click());
  zone.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input && input.click(); } });
  if (input) input.addEventListener('change', e => accept(e.target.files));
  ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('is-over'); }));
  zone.addEventListener('drop', e => accept(e.dataTransfer.files));

  return { files: picked, render };
};

/* ---------- tiny horizontal bar chart ---------- */
UI.bars = function (target, data) {
  const host = typeof target === 'string' ? document.querySelector(target) : target;
  if (!host) return;
  const max = Math.max.apply(null, data.map(d => d.v).concat([1]));
  host.innerHTML = '<div class="bars">' + data.map(d =>
    '<div class="bar__row"><div>' + UI.esc(d.k) + '</div>' +
    '<div class="bar__track"><div class="bar__fill" style="width:' + Math.round(d.v / max * 100) + '%' +
      (d.color ? ';background:' + d.color : '') + '"></div></div>' +
    '<div class="bar__n">' + d.v + '</div></div>').join('') + '</div>';
};

/* ---------- inline validation for the wizard & forms ---------- */
UI.validate = function (scope) {
  let ok = true;
  scope.querySelectorAll('[data-required]').forEach(el => {
    const field = el.closest('.field') || el.closest('.panel__body');
    const empty = !String(el.value || '').trim();
    if (field) field.classList.toggle('has-error', empty);
    if (empty && ok) { el.focus(); ok = false; }
  });
  return ok;
};

/* clear the error state as soon as someone starts fixing it */
document.addEventListener('input', e => {
  const f = e.target.closest('.field.has-error');
  if (f && String(e.target.value || '').trim()) f.classList.remove('has-error');
});

/* every control that would need a server is marked data-stub */
document.addEventListener('click', e => {
  const b = e.target.closest('[data-stub]');
  if (b) { e.preventDefault(); UI.stub(b.dataset.stub); }
});
