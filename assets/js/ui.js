// Shared formatting, tables, dialogs, forms and upload previews.

const UI = {};

UI.esc = v => String(v == null ? '' : v)
  .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;').replaceAll('"', '&quot;');

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
UI.compare = function (a, b) {
  if (a > b) return 1;
  if (a < b) return -1;
  return 0;
};

UI.size = function (b) {
  if (b == null) return '—';
  return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB';
};

UI.badge = function (status, dict) {
  const map = dict || AVIC.labels.status;
  return '<span class="badge badge--' + UI.esc(status) + '">' + UI.esc(map[status] || status) + '</span>';
};

UI.sla = function (due) {
  if (!due) return '<span class="muted">—</span>';
  const days = Math.ceil((new Date(due) - Date.now()) / 86400000);
  if (days < 0)  return '<span class="sla sla--late">' + Math.abs(days) + ' d over</span>';
  if (days <= 3) return '<span class="sla sla--warn">' + days + ' d left</span>';
  return '<span class="sla sla--ok">' + days + ' d left</span>';
};


UI.qs = key => new URLSearchParams(location.search).get(key);

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

UI.modal = function (opts) {
  const wrap = document.createElement('div');
  wrap.className = 'overlay';
  wrap.innerHTML =
    '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="dialog-title">' +
      '<div class="modal__head"><h2 id="dialog-title">' + UI.esc(opts.title) + '</h2></div>' +
      '<div class="modal__body">' + (opts.body || '') + '</div>' +
      '<div class="modal__foot">' +
        '<button class="btn" data-x>' + UI.esc(opts.cancel || 'Cancel') + '</button>' +
        '<button class="btn ' + (opts.danger ? 'btn--danger' : 'btn--primary') + '" data-ok>' + UI.esc(opts.confirm || 'Confirm') + '</button>' +
      '</div>' +
    '</div>';
  document.body.appendChild(wrap);
  const previousFocus = document.activeElement;
  let busy = false;
  const close = () => { if (busy) return; document.removeEventListener('keydown', keyboard); wrap.remove(); previousFocus?.focus(); };
  function keyboard(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    if (e.key === 'Tab') {
      const controls = [...wrap.querySelectorAll('button, input, select, textarea, a[href]')].filter(el => !el.disabled);
      const first = controls[0], last = controls[controls.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }
  wrap.querySelector('[data-x]').onclick = close;
  wrap.addEventListener('click', e => { if (e.target === wrap) close(); });
  document.addEventListener('keydown', keyboard);
  wrap.querySelector('[data-ok]').onclick = async () => {
    const form = wrap.querySelector('form');
    if (form && !form.reportValidity()) return;
    const data = form ? Object.fromEntries(new FormData(form).entries()) : {};
    busy = true;
    const controls = [...wrap.querySelectorAll('button,input,select,textarea')];
    const disabled = controls.map(control => control.disabled);
    controls.forEach(control => control.disabled = true);
    const button = wrap.querySelector('[data-ok]');
    const label = button.textContent; button.textContent = 'Saving…';
    try {
      const result = opts.onConfirm ? await opts.onConfirm(data) : undefined;
      busy = false;
      if (result !== false) close();
    } catch (error) {
      UI.toast('Could not complete the request. Your entries have been kept; try again.', 'bad');
    } finally {
      busy = false;
      controls.forEach((control, index) => control.disabled = disabled[index]);
      button.textContent = label;
    }
  };
  const first = wrap.querySelector('input, select, textarea, [data-x]');
  if (first) first.focus();
  return wrap;
};

UI.confirm = (title, body, onYes, label) =>
  UI.modal({ title, body: '<p>' + body + '</p>', confirm: label || 'Confirm', danger: /reject|delete|remove|close/i.test(title), onConfirm: onYes });

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
        if (av == null) return 1;
        if (bv == null) return -1;
        return UI.compare(av, bv) * (sortDir === 'asc' ? 1 : -1);
      });
    }
    if (!view.length) {
      host.innerHTML = '<div class="empty"><div class="empty__t">' + UI.esc(rows.length ? 'No matching results' : cfg.emptyTitle || 'Nothing here yet') + '</div>' +
        '<div class="empty__d">' + (rows.length ? 'Try another search or change the filters.' : cfg.emptyBody || '') + '</div>' + (cfg.emptyAction || '') + '</div>';
      return;
    }
    const arrow = sortDir === 'asc' ? '▲' : '▼';
    const head = cfg.cols.map(c =>
      '<th scope="col"' + (c.sortable ? ' aria-sort="' + (sortKey === c.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none') + '"' : '') + '>' +
      (c.sortable ? '<button type="button" class="sort-button" data-k="' + c.key + '">' : '') +
      UI.esc(c.label) + (sortKey === c.key ? ' <span class="arrow" aria-hidden="true">' + arrow + '</span>' : '') +
      (c.sortable ? '</button>' : '') + '</th>').join('');
    const body = view.map(r =>
      '<tr' + (cfg.rowClass ? ' class="' + cfg.rowClass(r) + '"' : '') + '>' +
      cfg.cols.map(c => '<td>' + c.cell(r) + '</td>').join('') + '</tr>').join('');
    host.innerHTML = '<div class="scroll-x"><table class="tbl"><thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table></div>';
    host.querySelectorAll('.sort-button').forEach(th => th.onclick = () => {
      const k = th.dataset.k;
      sortDir = (sortKey === k && sortDir === 'desc') ? 'asc' : 'desc';
      sortKey = k; draw();
      host.querySelector('.sort-button[data-k="' + k + '"]')?.focus();
    });
    // Keep native links for keyboard/new-tab use, but let the full row open its main link.
    host.querySelectorAll('tbody tr').forEach(row => {
      const link = row.querySelector('a[href]');
      if (!link) return;
      row.classList.add('is-clickable');
      row.onclick = event => {
        if (event.target.closest('a,button,input,select,textarea,label,summary') || window.getSelection()?.toString()) return;
        if (event.ctrlKey || event.metaKey) window.open(link.href, '_blank', 'noopener');
        else link.click();
      };
    });
    if (cfg.afterDraw) cfg.afterDraw(host, view);
  }

  draw();
  return { redraw: draw, setRows(r) { rows = r; draw(); }, setFilter(f) { cfg.filter = f; draw(); } };
};

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

UI.dropzone = function (zoneSel, listSel, onChange) {
  const zone = document.querySelector(zoneSel);
  const list = document.querySelector(listSel);
  if (!zone || !list) return;
  const input = document.getElementById(zone.getAttribute('aria-controls'));
  const picked = [];

  function render() {
    list.innerHTML = picked.map((f, i) =>
      '<div class="file">' +
        '<div class="file__thumb">' + (f.preview ? '<img alt="" src="' + f.preview + '">' : '<span>PDF</span>') + '</div>' +
          '<div class="file__meta">' +
          '<div class="file__n" title="' + UI.esc(f.name) + '">' + UI.esc(f.name) + '</div>' +
          '<div class="file__s">' + UI.size(f.size) + ' · <button type="button" class="file__x" data-rm="' + i + '">Remove</button></div>' +
        '</div>' +
      '</div>').join('');
    list.querySelectorAll('[data-rm]').forEach(b => b.onclick = () => {
      picked.splice(+b.dataset.rm, 1); render(); if (onChange) onChange(picked);
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

  zone.addEventListener('click', event => { if (event.target !== input) input?.click(); });
  if (input) input.addEventListener('change', e => accept(e.target.files));
  ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('is-over'); }));
  zone.addEventListener('drop', e => accept(e.dataTransfer.files));

  return { files: picked, render };
};

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

UI.validate = function (scope) {
  let ok = true;
  scope.querySelectorAll('[data-required]').forEach(el => {
    const field = el.closest('.field') || el.closest('.panel__body');
    const empty = !String(el.value || '').trim();
    if (field) field.classList.toggle('has-error', empty);
    el.setAttribute('aria-invalid', String(empty));
    const error = field?.querySelector('.err');
    if (error && el.id) { error.id = el.id + '-error'; el.setAttribute('aria-describedby', error.id); }
    if (empty && ok) { el.focus(); ok = false; }
  });
  return ok;
};

document.addEventListener('input', e => {
  const f = e.target.closest('.field.has-error');
  if (f && String(e.target.value || '').trim()) f.classList.remove('has-error');
});

