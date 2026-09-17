/* AVIC Portal — claimant desk. Claims are read through
   AVIC.claimsFor(session) only, filtered on user_id. */

const Claimant = {};

/* ---------------- dashboard ---------------- */
Claimant.dashboard = function (s) {
  const mine = AVIC.claimsFor(s);
  const open = mine.filter(c => !['paid', 'closed', 'rejected'].includes(c.status));
  const paid = mine.filter(c => c.status === 'paid');
  const paidTotal = paid.reduce((t, c) => t + (c.approved_amount || 0), 0);

  document.getElementById('k-open').innerHTML = open.length;
  document.getElementById('k-draft').innerHTML = mine.filter(c => c.status === 'draft').length;
  document.getElementById('k-paid').innerHTML = UI.money(paidTotal);
  document.getElementById('k-policies').innerHTML = AVIC.policiesFor(s).filter(p => p.status === 'active').length;

  document.getElementById('greeting').textContent = 'Hello, ' + s.name.split(' ')[0];

  /* active claim card */
  const active = open.sort((a, b) => (b.submitted_at || '').localeCompare(a.submitted_at || ''))[0];
  const host = document.getElementById('active-claim');
  if (active) {
    host.innerHTML =
      '<div class="panel__head"><div><h2>' + UI.esc(active.claim_number) + '</h2>' +
      '<div class="sub">' + UI.esc(AVIC.labels.claim_type[active.claim_type]) + ' · reported ' + UI.date(active.incident_date) + '</div></div>' +
      '<div class="spacer"></div>' + UI.badge(active.status) + '</div>' +
      '<div class="panel__body">' + Claimant.timelineHTML(active) + '</div>' +
      '<div class="panel__foot"><a class="btn btn--sm" href="claim-detail.html?id=' + active.id + '">Open claim</a>' +
      (active.due_date ? '<span class="muted small">Decision expected by ' + UI.date(active.due_date) + '</span>' : '') + '</div>';
  } else {
    host.innerHTML = '<div class="empty"><div class="empty__t">No claim in progress</div>' +
      '<div class="empty__d">When something happens to your vehicle, file it here and track it to payout.</div>' +
      '<a class="btn btn--primary" href="claim-new.html">File a claim</a></div>';
  }

  /* recent claims */
  UI.table('#recent', {
    rows: mine.slice().sort((a, b) => b.created_at.localeCompare(a.created_at)).slice(0, 5),
    cols: [
      { label: 'Claim', cell: c => '<a class="link mono" href="claim-detail.html?id=' + c.id + '">' + c.claim_number + '</a>' },
      { label: 'Type', cell: c => UI.esc(AVIC.labels.claim_type[c.claim_type]) },
      { label: 'Incident', cell: c => UI.date(c.incident_date) },
      { label: 'Amount', cell: c => '<span class="mono">' + UI.money(c.approved_amount != null ? c.approved_amount : c.estimated_damage) + '</span>' },
      { label: 'Status', cell: c => UI.badge(c.status) }
    ],
    emptyTitle: 'Nothing filed yet'
  });
};

Claimant.timelineHTML = function (c) {
  const ladder = c.status === 'rejected'
    ? ['draft', 'submitted', 'under_review', 'rejected']
    : AVIC.statusLadder;
  const at = ladder.indexOf(c.status);
  const stamps = {
    draft: c.created_at, submitted: c.submitted_at, under_review: c.submitted_at,
    approved: c.reviewed_at, rejected: c.reviewed_at, paid: c.resolved_at
  };
  return '<ol class="timeline">' + ladder.map((st, i) =>
    '<li class="' + (i < at ? 'is-done' : i === at ? 'is-current' : '') + '">' +
      '<span class="dot"></span><div>' +
      '<div class="timeline__t">' + UI.esc(AVIC.labels.status[st]) + '</div>' +
      '<div class="timeline__d">' + (i <= at ? UI.dateTime(stamps[st]) : 'Not reached yet') + '</div>' +
      (st === c.status && c.rejection_reason ? '<div class="note note--stop" style="margin-top:8px">' + UI.esc(c.rejection_reason) + '</div>' : '') +
      '</div></li>').join('') + '</ol>';
};

/* ---------------- claims list ---------------- */
Claimant.claims = function (s) {
  const rows = AVIC.claimsFor(s);
  let q = '', st = '';
  const t = UI.table('#claims-table', {
    rows,
    sortKey: 'created_at',
    filter: c => (!st || c.status === st) &&
      (!q || (c.claim_number + ' ' + c.incident_location + ' ' + c.incident_description).toLowerCase().includes(q)),
    cols: [
      { label: 'Claim number', key: 'claim_number', sortable: true, cell: c => '<a class="link mono" href="claim-detail.html?id=' + c.id + '">' + c.claim_number + '</a>' },
      { label: 'Type', cell: c => UI.esc(AVIC.labels.claim_type[c.claim_type]) },
      { label: 'Incident date', key: 'incident_date', sortable: true, cell: c => UI.date(c.incident_date) },
      { label: 'Where', cell: c => UI.esc(c.incident_location || '—') },
      { label: 'Claimed', key: 'estimated_damage', sortable: true, cell: c => '<span class="mono">' + UI.money(c.estimated_damage) + '</span>' },
      { label: 'Status', key: 'status', sortable: true, cell: c => UI.badge(c.status) },
      { label: '', cell: c => c.status === 'draft'
          ? '<a class="btn btn--sm" href="claim-new.html?draft=' + c.id + '">Continue</a>'
          : '<a class="btn btn--sm" href="claim-detail.html?id=' + c.id + '">View</a>' }
    ],
    emptyTitle: 'No claims match',
    emptyBody: 'Try clearing the filters, or file a new claim.',
    emptyAction: '<a class="btn btn--primary" href="claim-new.html">File a claim</a>'
  });
  document.getElementById('f-q').oninput = e => { q = e.target.value.toLowerCase(); t.redraw(); };
  document.getElementById('f-status').onchange = e => { st = e.target.value; t.redraw(); };
};

/* ---------------- claim detail ---------------- */
Claimant.detail = function (s) {
  const c = AVIC.claim(+UI.qs('id'));
  if (!AVIC.canSeeClaim(s, c)) {
    location.replace(AVIC.url('pages/errors/403.html?reason=not-yours'));
    return;
  }
  const pol = AVIC.policy(c.policy_id);
  const adj = c.adjuster_id ? AVIC.user(c.adjuster_id) : null;
  const docs = AVIC.docsFor(c.id);
  const est = AVIC.estimatesFor(c.id)[0];
  const pay = AVIC.payoutFor(c.id);

  document.getElementById('c-number').textContent = c.claim_number;
  document.getElementById('c-sub').innerHTML =
    AVIC.labels.claim_type[c.claim_type] + ' · filed ' + UI.date(c.created_at) +
    (adj ? ' · handled by ' + UI.esc(adj.full_name) : ' · not yet assigned');
  document.getElementById('c-badge').innerHTML = UI.badge(c.status);
  document.getElementById('c-timeline').innerHTML = Claimant.timelineHTML(c);

  document.getElementById('c-facts').innerHTML =
    '<dl class="kv">' +
      '<dt>Incident date</dt><dd>' + UI.date(c.incident_date) + '</dd>' +
      '<dt>Location</dt><dd>' + UI.esc(c.incident_location || '—') + '</dd>' +
      '<dt>Police reference</dt><dd class="mono">' + UI.esc(c.police_report_ref || '—') + '</dd>' +
      '<dt>What happened</dt><dd>' + UI.esc(c.incident_description) + '</dd>' +
      '<dt>Policy</dt><dd class="mono">' + UI.esc(pol.policy_number) + '</dd>' +
      '<dt>Vehicle</dt><dd>' + UI.esc([pol.vehicle_year, pol.vehicle_make, pol.vehicle_model].join(' ')) + ' · ' + UI.esc(pol.vehicle_plate) + '</dd>' +
      '<dt>Cover limit</dt><dd class="mono">' + UI.money(pol.coverage_limit) + '</dd>' +
      '<dt>Amount claimed</dt><dd class="mono">' + UI.money(c.estimated_damage) + '</dd>' +
      '<dt>Amount approved</dt><dd class="mono">' + (c.approved_amount != null ? UI.money(c.approved_amount) : 'Not decided') + '</dd>' +
    '</dl>';

  document.getElementById('c-docs').innerHTML = docs.length ? docs.map(d =>
    '<div class="notif"><div class="notif__bar"></div><div style="flex:1">' +
      '<div class="notif__t">' + UI.esc(d.original_name) + '</div>' +
      '<div class="notif__m">' + UI.esc(AVIC.labels.doc_type[d.doc_type]) + ' · ' + UI.size(d.file_size) + ' · uploaded ' + UI.date(d.uploaded_at) + '</div>' +
    '</div>' + (d.is_verified ? '<span class="badge badge--verified">Verified</span>' : '<span class="tag">Awaiting check</span>') + '</div>'
  ).join('') : '<div class="empty"><div class="empty__d">No documents attached.</div></div>';

  const estHost = document.getElementById('c-estimate');
  estHost.innerHTML = est
    ? '<dl class="kv"><dt>Garage</dt><dd>' + UI.esc(est.garage_name) + '</dd>' +
      '<dt>Parts</dt><dd class="mono">' + UI.money(est.parts_cost) + '</dd>' +
      '<dt>Labour</dt><dd class="mono">' + UI.money(est.labor_cost) + '</dd>' +
      '<dt>Other</dt><dd class="mono">' + UI.money(est.other_cost) + '</dd>' +
      '<dt>Total quoted</dt><dd class="mono"><b>' + UI.money(est.total_estimate) + '</b></dd>' +
      '<dt>Repair time</dt><dd>' + est.repair_days + ' working days</dd>' +
      '<dt>Status</dt><dd>' + UI.badge(est.status, AVIC.labels.payout_status) + '</dd></dl>'
    : '<p class="muted small">No garage estimate has been submitted for this claim yet.</p>';

  const payHost = document.getElementById('c-payout');
  payHost.innerHTML = pay
    ? '<dl class="kv"><dt>Reference</dt><dd class="mono">' + UI.esc(pay.reference_number) + '</dd>' +
      '<dt>Amount</dt><dd class="mono">' + UI.money(pay.amount) + '</dd>' +
      '<dt>Method</dt><dd>' + UI.esc(AVIC.labels.payment_method[pay.payment_method]) + '</dd>' +
      '<dt>Status</dt><dd>' + UI.badge(pay.status, AVIC.labels.payout_status) + '</dd>' +
      '<dt>Processed</dt><dd>' + UI.dateTime(pay.processed_at) + '</dd></dl>'
    : '<p class="muted small">A payout is created once an adjuster approves the claim.</p>';

  /* actions that depend on status */
  const acts = document.getElementById('c-actions');
  if (c.status === 'pending_docs') {
    acts.innerHTML = '<a class="btn btn--primary btn--sm" href="claim-new.html?add=' + c.id + '">Add the missing documents</a>';
  } else if (c.status === 'draft') {
    acts.innerHTML = '<a class="btn btn--primary btn--sm" href="claim-new.html?draft=' + c.id + '">Continue this draft</a>';
  } else {
    acts.innerHTML = '<button class="btn btn--sm" data-stub="Claim summary PDF">Download summary</button>';
  }
};

/* ---------------- policies ---------------- */
Claimant.policies = function (s) {
  const host = document.getElementById('policy-list');
  const mine = AVIC.policiesFor(s);
  host.innerHTML = mine.map(p =>
    '<div class="panel"><div class="panel__head">' +
      '<div><h2 class="mono">' + UI.esc(p.policy_number) + '</h2>' +
      '<div class="sub">' + UI.esc([p.vehicle_year, p.vehicle_make, p.vehicle_model].join(' ')) + ' · ' + UI.esc(p.vehicle_plate) + '</div></div>' +
      '<div class="spacer"></div>' + UI.badge(p.status, { active: 'Active', expired: 'Expired', cancelled: 'Cancelled' }) + '</div>' +
      '<div class="panel__body"><dl class="kv">' +
        '<dt>Cover</dt><dd>' + UI.esc(AVIC.labels.coverage[p.coverage_type]) + '</dd>' +
        '<dt>Cover limit</dt><dd class="mono">' + UI.money(p.coverage_limit) + '</dd>' +
        '<dt>Premium</dt><dd class="mono">' + UI.money(p.premium) + ' per year</dd>' +
        '<dt>Period</dt><dd>' + UI.date(p.start_date) + ' to ' + UI.date(p.end_date) + '</dd>' +
        '<dt>Chassis (VIN)</dt><dd class="mono">' + UI.esc(p.vehicle_vin) + '</dd>' +
      '</dl></div>' +
      '<div class="panel__foot">' + (p.status === 'active'
        ? '<a class="btn btn--sm btn--primary" href="claim-new.html?policy=' + p.id + '">File a claim on this policy</a>'
        : '<span class="small">Claims cannot be filed against a policy that is not active.</span>') + '</div>' +
    '</div>').join('');
};

/* ---------------- claim wizard ---------------- */
Claimant.wizard = function (s) {
  const form = document.getElementById('wizard');
  const steps = [].slice.call(document.querySelectorAll('.wizstep'));
  const marks = [].slice.call(document.querySelectorAll('.step'));
  let at = 0;
  const draft = { files: [] };

  const policies = AVIC.policiesFor(s);
  const sel = document.getElementById('policy_id');
  sel.innerHTML = '<option value="">Choose a policy…</option>' + policies.map(p =>
    '<option value="' + p.id + '"' + (p.status !== 'active' ? ' disabled' : '') + '>' +
    p.policy_number + ' — ' + [p.vehicle_year, p.vehicle_make, p.vehicle_model].join(' ') +
    (p.status !== 'active' ? ' (' + p.status + ')' : '') + '</option>').join('');

  const preset = UI.qs('policy');
  if (preset) sel.value = preset;

  sel.onchange = () => {
    const p = AVIC.policy(+sel.value);
    const box = document.getElementById('vehicle-box');
    if (!p) { box.innerHTML = '<p class="muted small">Pick a policy and the vehicle on cover appears here.</p>'; return; }
    const expired = p.status !== 'active';
    box.innerHTML =
      (expired ? '<div class="note note--stop">This policy is ' + p.status + '. A claim filed against it will be rejected at review.</div>' : '') +
      '<dl class="kv">' +
      '<dt>Vehicle</dt><dd>' + UI.esc([p.vehicle_year, p.vehicle_make, p.vehicle_model].join(' ')) + '</dd>' +
      '<dt>Number plate</dt><dd class="mono">' + UI.esc(p.vehicle_plate) + '</dd>' +
      '<dt>Cover</dt><dd>' + UI.esc(AVIC.labels.coverage[p.coverage_type]) + ' up to <span class="mono">' + UI.money(p.coverage_limit) + '</span></dd>' +
      '<dt>Valid until</dt><dd>' + UI.date(p.end_date) + '</dd></dl>';
  };
  if (preset) sel.onchange();

  /* claim type as clickable choices */
  document.querySelectorAll('.choice[data-type]').forEach(ch => ch.onclick = () => {
    document.querySelectorAll('.choice[data-type]').forEach(x => x.classList.remove('is-on'));
    ch.classList.add('is-on');
    document.getElementById('claim_type').value = ch.dataset.type;
    document.getElementById('claim_type').dispatchEvent(new Event('input', { bubbles: true }));
  });

  UI.counters(form);
  const zone = UI.dropzone('#dropzone', '#filelist', files => { draft.files = files; });

  function show(i) {
    at = i;
    steps.forEach((el, n) => el.classList.toggle('hidden', n !== i));
    marks.forEach((el, n) => {
      el.classList.toggle('is-on', n === i);
      el.classList.toggle('is-done', n < i);
    });
    document.getElementById('back').classList.toggle('hidden', i === 0);
    document.getElementById('next').classList.toggle('hidden', i === steps.length - 1);
    document.getElementById('submit').classList.toggle('hidden', i !== steps.length - 1);
    if (i === steps.length - 1) summarise();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function summarise() {
    const p = AVIC.policy(+sel.value);
    const v = n => (form.elements[n] && form.elements[n].value) || '';
    const parts = +v('parts_cost') || 0, labour = +v('labor_cost') || 0, other = +v('other_cost') || 0;
    document.getElementById('summary').innerHTML =
      '<dl class="kv">' +
      '<dt>Policy</dt><dd class="mono">' + UI.esc(p ? p.policy_number : '—') + '</dd>' +
      '<dt>Vehicle</dt><dd>' + UI.esc(p ? [p.vehicle_year, p.vehicle_make, p.vehicle_model].join(' ') : '—') + '</dd>' +
      '<dt>Incident</dt><dd>' + UI.esc(AVIC.labels.claim_type[v('claim_type')] || '—') + ' on ' + UI.date(v('incident_date')) + '</dd>' +
      '<dt>Location</dt><dd>' + UI.esc(v('incident_location') || '—') + '</dd>' +
      '<dt>Description</dt><dd>' + UI.esc(v('incident_description') || '—') + '</dd>' +
      '<dt>Police reference</dt><dd class="mono">' + UI.esc(v('police_report_ref') || 'None') + '</dd>' +
      '<dt>Documents</dt><dd>' + (draft.files.length ? draft.files.length + ' attached' : 'None attached') + '</dd>' +
      '<dt>Garage</dt><dd>' + UI.esc(v('garage_name') || 'Not chosen yet') + '</dd>' +
      '<dt>Estimate</dt><dd class="mono">' + UI.money(parts + labour + other) + '</dd>' +
      '</dl>';
  }

  document.getElementById('next').onclick = () => {
    if (!UI.validate(steps[at])) { UI.toast('Complete the highlighted fields before moving on.', 'bad'); return; }
    show(at + 1);
  };
  document.getElementById('back').onclick = () => show(at - 1);
  marks.forEach((m, i) => m.onclick = () => { if (i < at) show(i); });

  /* running estimate total */
  ['parts_cost', 'labor_cost', 'other_cost'].forEach(n => {
    if (form.elements[n]) form.elements[n].oninput = () => {
      const t = (+form.elements.parts_cost.value || 0) + (+form.elements.labor_cost.value || 0) + (+form.elements.other_cost.value || 0);
      document.getElementById('est-total').innerHTML = UI.money(t);
    };
  });

  /* auto-save indicator — the plan's sessionStorage draft tick */
  const stamp = document.getElementById('autosave');
  function saveDraft() {
    const data = Object.fromEntries(new FormData(form).entries());
    sessionStorage.setItem('avic.draft', JSON.stringify(data));
    stamp.textContent = 'Draft saved ' + new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
  }
  form.addEventListener('input', () => { stamp.textContent = 'Unsaved changes'; });
  setInterval(saveDraft, 60000);
  document.getElementById('save-draft').onclick = () => { saveDraft(); UI.toast('Draft saved. Pick it up from My claims.', 'ok'); };

  /* restore a draft if one was left behind */
  const kept = sessionStorage.getItem('avic.draft');
  if (kept && !UI.qs('policy')) {
    try {
      const d = JSON.parse(kept);
      Object.keys(d).forEach(k => { if (form.elements[k] && d[k]) form.elements[k].value = d[k]; });
      if (d.policy_id) sel.onchange();
      if (d.claim_type) {
        const ch = document.querySelector('.choice[data-type="' + d.claim_type + '"]');
        if (ch) ch.classList.add('is-on');
      }
      stamp.textContent = 'Draft restored';
    } catch (e) { /* ignore */ }
  }

  document.getElementById('submit').onclick = () => {
    if (!form.elements.confirm_true.checked) { UI.toast('Tick the declaration before submitting.', 'bad'); return; }
    UI.modal({
      title: 'Submit this claim?',
      body: '<p>Once submitted you cannot edit the details yourself. An adjuster will be assigned and you will get a decision within ' +
            AVIC.settings.claims_sla_days + ' days.</p>',
      confirm: 'Submit claim',
      onConfirm: () => {
        sessionStorage.removeItem('avic.draft');
        UI.toast('Claim submitted in the prototype — nothing was sent to a server.', 'ok');
        setTimeout(() => location.href = 'claims.html', 900);
      }
    });
  };

  show(0);
};

/* ---------------- boot ---------------- */
AVIC.boot(function (s) {
  ({
    dashboard: Claimant.dashboard,
    claims: Claimant.claims,
    'claim-detail': Claimant.detail,
    'claim-new': Claimant.wizard,
    policies: Claimant.policies,
    notifications: AVIC.pages.notifications,
    profile: AVIC.pages.profile
  }[document.body.dataset.page] || function () {})(s);
});
