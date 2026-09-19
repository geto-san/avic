/* ============================================================
   AVIC Portal — administration
   The only desk with an unfiltered view. Everything an admin
   does here is written to the audit log in the built system,
   including viewing a claimant's account as them.
   ============================================================ */

const Admin = {};

Admin.dashboard = function () {
  const all = AVIC.claims;
  const open = all.filter(c => ['submitted', 'under_review', 'pending_docs'].includes(c.status));
  const paidOut = AVIC.payouts.filter(p => p.status === 'completed').reduce((t, p) => t + p.amount, 0);
  const resolved = all.filter(c => c.resolved_at && c.submitted_at);
  const avgDays = resolved.length
    ? Math.round(resolved.reduce((t, c) => t + (new Date(c.resolved_at) - new Date(c.submitted_at)) / 86400000, 0) / resolved.length)
    : 0;

  document.getElementById('k-open').textContent = open.length;
  document.getElementById('k-avg').textContent = avgDays + ' days';
  document.getElementById('k-paid').innerHTML = UI.money(paidOut);
  document.getElementById('k-users').textContent = AVIC.users.filter(u => u.status === 'pending').length;

  UI.bars('#by-status', AVIC.statusLadder.concat(['pending_docs', 'rejected'])
    .map(st => ({ k: AVIC.labels.status[st], v: all.filter(c => c.status === st).length })));

  UI.bars('#by-role', Object.keys(AVIC.roles)
    .map(r => ({ k: AVIC.roles[r].label, v: AVIC.users.filter(u => u.role === r).length })));

  document.getElementById('activity').innerHTML = AVIC.audit.slice()
    .sort((a, b) => b.created_at.localeCompare(a.created_at)).slice(0, 6).map(a =>
    '<div class="notif"><div class="notif__bar"></div><div>' +
      '<div class="notif__t mono">' + UI.esc(a.action) + '</div>' +
      '<div class="notif__m">' + UI.esc(AVIC.user(a.user_id) ? AVIC.user(a.user_id).full_name : 'system') +
        ' · ' + UI.esc(a.entity_type) + ' #' + a.entity_id + '</div>' +
      '<div class="notif__d">' + UI.dateTime(a.created_at) + '</div></div></div>').join('');
};

Admin.claims = function () {
  let q = '', st = '';
  const t = UI.table('#claims-table', {
    rows: AVIC.claims.slice(), sortKey: 'created_at',
    filter: c => (!st || c.status === st) &&
      (!q || (c.claim_number + ' ' + AVIC.user(c.user_id).full_name + ' ' + c.incident_location).toLowerCase().includes(q)),
    cols: [
      { label: 'Claim', key: 'claim_number', sortable: true, cell: c => '<a class="link mono" href="claim-detail.html?id=' + c.id + '">' + c.claim_number + '</a>' },
      { label: 'Claimant', cell: c => UI.esc(AVIC.user(c.user_id).full_name) },
      { label: 'Adjuster', cell: c => c.adjuster_id ? UI.esc(AVIC.user(c.adjuster_id).full_name) : '<span class="muted">unassigned</span>' },
      { label: 'Type', cell: c => UI.esc(AVIC.labels.claim_type[c.claim_type]) },
      { label: 'Claimed', key: 'estimated_damage', sortable: true, cell: c => '<span class="mono">' + UI.money(c.estimated_damage) + '</span>' },
      { label: 'Approved', cell: c => '<span class="mono">' + UI.money(c.approved_amount) + '</span>' },
      { label: 'SLA', key: 'due_date', sortable: true, cell: c => ['submitted', 'under_review', 'pending_docs'].includes(c.status) ? UI.sla(c.due_date) : '<span class="muted">—</span>' },
      { label: 'Status', key: 'status', sortable: true, cell: c => UI.badge(c.status) }
    ],
    emptyTitle: 'No claims match'
  });
  document.getElementById('f-q').oninput = e => { q = e.target.value.toLowerCase(); t.redraw(); };
  document.getElementById('f-status').onchange = e => { st = e.target.value; t.redraw(); };
  document.getElementById('export').onclick = () => UI.stub('CSV export');
};

Admin.claimDetail = function () {
  const c = AVIC.claim(+UI.qs('id'));
  if (!c) { location.replace(AVIC.url('pages/errors/404.html')); return; }
  const claimant = AVIC.user(c.user_id);
  const pol = AVIC.policy(c.policy_id);
  const docs = AVIC.docsFor(c.id);
  const est = AVIC.estimatesFor(c.id)[0];
  const pay = AVIC.payoutFor(c.id);

  document.getElementById('c-number').textContent = c.claim_number;
  document.getElementById('c-sub').textContent = AVIC.labels.claim_type[c.claim_type] + ' · ' + UI.date(c.incident_date);
  document.getElementById('c-badge').innerHTML = UI.badge(c.status);

  document.getElementById('c-facts').innerHTML =
    '<dl class="kv">' +
      '<dt>Claimant</dt><dd>' + UI.esc(claimant.full_name) + ' · ' + UI.esc(claimant.email) + '</dd>' +
      '<dt>Adjuster</dt><dd>' + (c.adjuster_id ? UI.esc(AVIC.user(c.adjuster_id).full_name) : 'unassigned') + '</dd>' +
      '<dt>Policy</dt><dd class="mono">' + UI.esc(pol.policy_number) + '</dd>' +
      '<dt>Cover limit</dt><dd class="mono">' + UI.money(pol.coverage_limit) + '</dd>' +
      '<dt>Claimed</dt><dd class="mono">' + UI.money(c.estimated_damage) + '</dd>' +
      '<dt>Approved</dt><dd class="mono">' + UI.money(c.approved_amount) + '</dd>' +
      '<dt>Garage quote</dt><dd class="mono">' + (est ? UI.money(est.total_estimate) : '—') + '</dd>' +
      '<dt>Documents</dt><dd>' + docs.filter(d => d.is_verified).length + ' verified of ' + docs.length + '</dd>' +
      '<dt>Payout</dt><dd>' + (pay ? '<span class="mono">' + UI.esc(pay.reference_number) + '</span> · ' + UI.badge(pay.status, AVIC.labels.payout_status) : 'none yet') + '</dd>' +
      '<dt>Description</dt><dd>' + UI.esc(c.incident_description) + '</dd>' +
    '</dl>';

  const acts = document.getElementById('c-actions');
  acts.innerHTML =
    (c.status === 'approved' && !pay ? '<button class="btn btn--sm btn--primary" id="mkpay">Create payout</button>' : '') +
    '<button class="btn btn--sm" id="reassign">Reassign adjuster</button>' +
    '<button class="btn btn--sm" data-stub="Claim summary PDF">Export summary</button>';

  const mk = document.getElementById('mkpay');
  if (mk) mk.onclick = () => location.href = 'payouts.html?create=' + c.id;
  document.getElementById('reassign').onclick = () => {
    const adjusters = AVIC.users.filter(u => u.role === 'adjuster' && u.status === 'active');
    UI.modal({
      title: 'Reassign this claim',
      body: '<form><div class="field"><label for="adj">Adjuster</label><select id="adj" name="adj">' +
        adjusters.map(a => '<option value="' + a.id + '"' + (a.id === c.adjuster_id ? ' selected' : '') + '>' +
          UI.esc(a.full_name) + ' — ' + AVIC.claims.filter(x => x.adjuster_id === a.id && ['submitted', 'under_review', 'pending_docs'].includes(x.status)).length +
          ' open</option>').join('') + '</select>' +
        '<div class="hint">Both adjusters are notified and the change is logged.</div></div></form>',
      confirm: 'Reassign',
      onConfirm: d => { c.adjuster_id = +d.adj; Admin.claimDetail(); UI.toast('Claim reassigned.', 'ok'); }
    });
  };
};

Admin.users = function (s) {
  let q = '', role = '';
  const t = UI.table('#users-table', {
    rows: AVIC.users.slice(), sortKey: 'created_at',
    filter: u => (!role || u.role === role) && (!q || (u.full_name + ' ' + u.email).toLowerCase().includes(q)),
    cols: [
      { label: 'Name', key: 'full_name', sortable: true, cell: u => '<b>' + UI.esc(u.full_name) + '</b><div class="tiny muted">' + UI.esc(u.email) + '</div>' },
      { label: 'Role', key: 'role', sortable: true, cell: u => '<span class="tag">' + UI.esc(AVIC.roles[u.role].label) + '</span>' },
      { label: 'Phone', cell: u => UI.esc(u.phone || '—') },
      { label: 'Joined', key: 'created_at', sortable: true, cell: u => UI.date(u.created_at) },
      { label: 'Last seen', cell: u => u.last_login ? UI.ago(u.last_login) : '<span class="muted">never</span>' },
      { label: 'Status', key: 'status', sortable: true, cell: u => UI.badge(u.status, { active: 'Active', pending: 'Awaiting approval', suspended: 'Suspended' }) },
      { label: '', cell: u => '<div class="btnrow">' +
          (u.status === 'pending' ? '<button class="btn btn--sm btn--primary" data-approve="' + u.id + '">Approve</button>' : '') +
          (u.status === 'active' && u.id !== s.id ? '<button class="btn btn--sm" data-suspend="' + u.id + '">Suspend</button>' : '') +
          (u.status === 'suspended' ? '<button class="btn btn--sm" data-restore="' + u.id + '">Restore</button>' : '') +
          (u.role === 'claimant' && u.status === 'active' ? '<button class="btn btn--sm btn--ghost" data-asuser="' + u.id + '">View as</button>' : '') +
        '</div>' }
    ],
    afterDraw(host) {
      host.querySelectorAll('[data-approve]').forEach(b => b.onclick = () => {
        const u = AVIC.user(+b.dataset.approve);
        UI.confirm('Approve ' + u.full_name + '?', 'They will be able to sign in as a ' + AVIC.roles[u.role].label.toLowerCase() + '.',
          () => { u.status = 'active'; t.redraw(); UI.toast('Account approved.', 'ok'); }, 'Approve account');
      });
      host.querySelectorAll('[data-suspend]').forEach(b => b.onclick = () => {
        const u = AVIC.user(+b.dataset.suspend);
        UI.confirm('Suspend ' + u.full_name + '?', 'Their session ends immediately and they cannot sign in again until restored.',
          () => { u.status = 'suspended'; t.redraw(); UI.toast('Account suspended.', 'ok'); }, 'Suspend account');
      });
      host.querySelectorAll('[data-restore]').forEach(b => b.onclick = () => {
        AVIC.user(+b.dataset.restore).status = 'active'; t.redraw(); UI.toast('Account restored.', 'ok');
      });
      host.querySelectorAll('[data-asuser]').forEach(b => b.onclick = () => {
        const u = AVIC.user(+b.dataset.asuser);
        UI.confirm('Open the portal as ' + u.full_name + '?',
          'You will see exactly what they see. The session is tagged and every action is logged until you return to your own account.',
          () => { AVIC.signIn(u.id, { impersonatedBy: s.id }); location.href = AVIC.homeFor(u.role); }, 'View as this user');
      });
    }
  });
  document.getElementById('f-q').oninput = e => { q = e.target.value.toLowerCase(); t.redraw(); };
  document.getElementById('f-role').onchange = e => { role = e.target.value; t.redraw(); };
};

Admin.payouts = function () {
  const t = UI.table('#pay-table', {
    rows: AVIC.payouts.slice(), sortKey: 'created_at',
    cols: [
      { label: 'Reference', key: 'reference_number', sortable: true, cell: p => '<span class="mono">' + UI.esc(p.reference_number) + '</span>' },
      { label: 'Claim', cell: p => '<a class="link mono" href="claim-detail.html?id=' + p.claim_id + '">' + AVIC.claim(p.claim_id).claim_number + '</a>' },
      { label: 'Payee', cell: p => UI.esc(AVIC.user(p.user_id).full_name) },
      { label: 'Amount', key: 'amount', sortable: true, cell: p => '<span class="mono"><b>' + UI.money(p.amount) + '</b></span>' },
      { label: 'Method', cell: p => UI.esc(AVIC.labels.payment_method[p.payment_method]) },
      { label: 'Raised', key: 'created_at', sortable: true, cell: p => UI.date(p.created_at) },
      { label: 'Status', key: 'status', sortable: true, cell: p => UI.badge(p.status, AVIC.labels.payout_status) },
      { label: '', cell: p => p.status === 'completed'
          ? '<button class="btn btn--sm" data-stub="Payout receipt PDF">Receipt</button>'
          : '<button class="btn btn--sm btn--primary" data-release="' + p.id + '">Release</button>' }
    ],
    afterDraw(host) {
      host.querySelectorAll('[data-release]').forEach(b => b.onclick = () => {
        const p = AVIC.payouts.find(x => x.id === +b.dataset.release);
        UI.confirm('Release ' + UI.moneyPlain(p.amount) + '?',
          'The claimant is notified and a receipt is generated. This cannot be undone from the portal.',
          () => { p.status = 'completed'; p.processed_at = new Date().toISOString(); t.redraw(); UI.toast('Payout released.', 'ok'); }, 'Release payout');
      });
    },
    emptyTitle: 'No payouts raised'
  });

  /* create-payout form, prefilled when arriving from a claim */
  const form = document.getElementById('payout-form');
  const pick = document.getElementById('claim_id');
  const eligible = AVIC.claims.filter(c => c.status === 'approved' && !AVIC.payoutFor(c.id));
  pick.innerHTML = '<option value="">Choose an approved claim…</option>' + eligible.map(c =>
    '<option value="' + c.id + '">' + c.claim_number + ' — ' + AVIC.user(c.user_id).full_name + '</option>').join('');
  const pre = UI.qs('create');
  if (pre) pick.value = pre;

  pick.onchange = () => {
    const c = AVIC.claim(+pick.value);
    document.getElementById('pay-context').innerHTML = c
      ? '<dl class="kv"><dt>Payee</dt><dd>' + UI.esc(AVIC.user(c.user_id).full_name) + '</dd>' +
        '<dt>Approved amount</dt><dd class="mono"><b>' + UI.money(c.approved_amount) + '</b></dd>' +
        '<dt>Cover limit</dt><dd class="mono">' + UI.money(AVIC.policy(c.policy_id).coverage_limit) + '</dd></dl>'
      : '<p class="muted small">Only approved claims without an existing payout can be paid.</p>';
    if (c) form.elements.amount.value = c.approved_amount;
  };
  pick.onchange();

  form.onsubmit = e => {
    e.preventDefault();
    if (!UI.validate(form)) return UI.toast('Fill in the highlighted fields.', 'bad');
    const c = AVIC.claim(+pick.value);
    if (!c) return UI.toast('Choose a claim to pay.', 'bad');
    if (+form.elements.amount.value > AVIC.policy(c.policy_id).coverage_limit)
      return UI.toast('The payout cannot exceed the policy cover limit.', 'bad');
    UI.confirm('Raise this payout?', UI.moneyPlain(+form.elements.amount.value) + ' to ' + AVIC.user(c.user_id).full_name + '.',
      () => { UI.toast('Payout raised in the prototype — nothing was saved.', 'ok'); form.reset(); }, 'Raise payout');
  };
};

Admin.audit = function () {
  let q = '', act = '';
  const t = UI.table('#audit-table', {
    rows: AVIC.audit.slice(), sortKey: 'created_at',
    filter: a => (!act || a.action.startsWith(act)) &&
      (!q || (a.action + ' ' + (AVIC.user(a.user_id) ? AVIC.user(a.user_id).full_name : '') + ' ' + a.entity_type).toLowerCase().includes(q)),
    cols: [
      { label: 'When', key: 'created_at', sortable: true, cell: a => UI.dateTime(a.created_at) },
      { label: 'Who', cell: a => { const u = AVIC.user(a.user_id); return u ? UI.esc(u.full_name) + ' <span class="tag">' + AVIC.roles[u.role].label + '</span>' : 'system'; } },
      { label: 'Action', key: 'action', sortable: true, cell: a => '<span class="mono">' + UI.esc(a.action) + '</span>' },
      { label: 'Entity', cell: a => UI.esc(a.entity_type) + ' #' + a.entity_id },
      { label: 'Change', cell: a => a.old_value || a.new_value
          ? '<span class="mono small">' + UI.esc(a.old_value == null ? '∅' : a.old_value) + ' → ' + UI.esc(a.new_value == null ? '∅' : a.new_value) + '</span>' : '—' },
      { label: 'IP', cell: a => '<span class="mono small">' + UI.esc(a.ip_address) + '</span>' }
    ],
    emptyTitle: 'No entries match'
  });
  document.getElementById('f-q').oninput = e => { q = e.target.value.toLowerCase(); t.redraw(); };
  document.getElementById('f-action').onchange = e => { act = e.target.value; t.redraw(); };
};

Admin.settings = function () {
  const f = document.getElementById('settings-form');
  Object.keys(AVIC.settings).forEach(k => { if (f.elements[k]) f.elements[k].value = AVIC.settings[k]; });
  f.onsubmit = e => {
    e.preventDefault();
    if (!UI.validate(f)) return UI.toast('Fill in the highlighted fields.', 'bad');
    UI.toast('Settings captured — the prototype does not persist them.', 'ok');
  };
};

Admin.reports = function () {
  const months = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
  const counts = [3, 5, 4, 7, 6, 8];
  UI.bars('#r-volume', months.map((m, i) => ({ k: m + ' 2026', v: counts[i] })));
  UI.bars('#r-type', Object.keys(AVIC.labels.claim_type).map(t =>
    ({ k: AVIC.labels.claim_type[t], v: AVIC.claims.filter(c => c.claim_type === t).length })));
  const settled = AVIC.payouts.filter(p => p.status === 'completed');
  document.getElementById('r-summary').innerHTML =
    '<dl class="kv">' +
      '<dt>Claims filed</dt><dd class="mono">' + AVIC.claims.length + '</dd>' +
      '<dt>Settled</dt><dd class="mono">' + settled.length + '</dd>' +
      '<dt>Total paid</dt><dd class="mono">' + UI.money(settled.reduce((t, p) => t + p.amount, 0)) + '</dd>' +
      '<dt>Rejection rate</dt><dd class="mono">' + Math.round(AVIC.claims.filter(c => c.status === 'rejected').length / AVIC.claims.length * 100) + '%</dd>' +
    '</dl>';
};

AVIC.boot(function (s) {
  ({
    dashboard: Admin.dashboard,
    claims: Admin.claims,
    'claim-detail': Admin.claimDetail,
    users: Admin.users,
    payouts: Admin.payouts,
    reports: Admin.reports,
    'audit-log': Admin.audit,
    settings: Admin.settings,
    notifications: AVIC.pages.notifications,
    profile: AVIC.pages.profile
  }[document.body.dataset.page] || function () {})(s);
});
