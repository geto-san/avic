/* AVIC Portal — adjuster desk. Only claims whose adjuster_id
   matches the session are listed or openable. */

const Adjuster = {};
const OPEN_STATES = ['submitted', 'under_review', 'pending_docs'];

Adjuster.dashboard = function (s) {
  const mine = AVIC.claimsFor(s);
  const open = mine.filter(c => OPEN_STATES.includes(c.status));
  const late = open.filter(c => c.due_date && new Date(c.due_date) < new Date());
  const decided = mine.filter(c => ['approved', 'rejected', 'paid', 'closed'].includes(c.status));

  document.getElementById('k-open').textContent = open.length;
  document.getElementById('k-late').textContent = late.length;
  document.getElementById('k-decided').textContent = decided.length;
  document.getElementById('k-exposure').innerHTML = UI.money(open.reduce((t, c) => t + (c.estimated_damage || 0), 0));
  document.getElementById('greeting').textContent = 'Your desk, ' + s.name.split(' ')[0];

  UI.bars('#mix', Object.entries(
    mine.reduce((m, c) => (m[AVIC.labels.status[c.status]] = (m[AVIC.labels.status[c.status]] || 0) + 1, m), {})
  ).map(([k, v]) => ({ k, v })));

  UI.table('#next-up', {
    rows: open.slice().sort((a, b) => (a.due_date || '').localeCompare(b.due_date || '')).slice(0, 5),
    cols: [
      { label: 'Claim', cell: c => '<a class="link mono" href="review.html?id=' + c.id + '">' + c.claim_number + '</a>' },
      { label: 'Type', cell: c => UI.esc(AVIC.labels.claim_type[c.claim_type]) },
      { label: 'Claimed', cell: c => '<span class="mono">' + UI.money(c.estimated_damage) + '</span>' },
      { label: 'Deadline', cell: c => UI.sla(c.due_date) },
      { label: 'Status', cell: c => UI.badge(c.status) }
    ],
    emptyTitle: 'Queue is clear'
  });
};

Adjuster.queue = function (s) {
  const rows = AVIC.claimsFor(s).filter(c => OPEN_STATES.includes(c.status));
  let q = '', st = '';
  const t = UI.table('#queue-table', {
    rows,
    sortKey: 'due_date', sortDir: 'asc',
    filter: c => (!st || c.status === st) && (!q || (c.claim_number + ' ' + c.incident_location).toLowerCase().includes(q)),
    rowClass: c => (c.due_date && new Date(c.due_date) < new Date()) ? 'is-mine' : '',
    cols: [
      { label: 'Claim', key: 'claim_number', sortable: true, cell: c => '<a class="link mono" href="review.html?id=' + c.id + '">' + c.claim_number + '</a>' },
      { label: 'Filed by', cell: c => UI.esc(AVIC.user(c.user_id).full_name) },
      { label: 'Type', cell: c => UI.esc(AVIC.labels.claim_type[c.claim_type]) },
      { label: 'Submitted', key: 'submitted_at', sortable: true, cell: c => UI.date(c.submitted_at) },
      { label: 'Claimed', key: 'estimated_damage', sortable: true, cell: c => '<span class="mono">' + UI.money(c.estimated_damage) + '</span>' },
      { label: 'Docs', cell: c => { const d = AVIC.docsFor(c.id); return d.filter(x => x.is_verified).length + ' of ' + d.length + ' checked'; } },
      { label: 'SLA', key: 'due_date', sortable: true, cell: c => UI.sla(c.due_date) },
      { label: 'Status', key: 'status', sortable: true, cell: c => UI.badge(c.status) }
    ],
    emptyTitle: 'Nothing waiting on you',
    emptyBody: 'New claims routed to you will appear here.'
  });
  document.getElementById('f-q').oninput = e => { q = e.target.value.toLowerCase(); t.redraw(); };
  document.getElementById('f-status').onchange = e => { st = e.target.value; t.redraw(); };
  document.getElementById('queue-count').textContent = rows.length + ' open ' + (rows.length === 1 ? 'claim' : 'claims');
};

Adjuster.decided = function (s) {
  UI.table('#decided-table', {
    rows: AVIC.claimsFor(s).filter(c => ['approved', 'rejected', 'paid', 'closed'].includes(c.status)),
    sortKey: 'reviewed_at',
    cols: [
      { label: 'Claim', key: 'claim_number', sortable: true, cell: c => '<a class="link mono" href="review.html?id=' + c.id + '">' + c.claim_number + '</a>' },
      { label: 'Decision', key: 'status', sortable: true, cell: c => UI.badge(c.status) },
      { label: 'Decided', key: 'reviewed_at', sortable: true, cell: c => UI.dateTime(c.reviewed_at) },
      { label: 'Claimed', cell: c => '<span class="mono">' + UI.money(c.estimated_damage) + '</span>' },
      { label: 'Approved', cell: c => '<span class="mono">' + UI.money(c.approved_amount) + '</span>' }
    ],
    emptyTitle: 'No decisions yet'
  });
};

Adjuster.estimates = function (s) {
  const mineIds = AVIC.claimsFor(s).map(c => c.id);
  const rows = AVIC.estimates.filter(e => mineIds.includes(e.claim_id));
  UI.table('#est-table', {
    rows, sortKey: 'created_at',
    cols: [
      { label: 'Claim', cell: e => '<a class="link mono" href="review.html?id=' + e.claim_id + '">' + AVIC.claim(e.claim_id).claim_number + '</a>' },
      { label: 'Garage', cell: e => UI.esc(e.garage_name) },
      { label: 'Parts', cell: e => '<span class="mono">' + UI.money(e.parts_cost) + '</span>' },
      { label: 'Labour', cell: e => '<span class="mono">' + UI.money(e.labor_cost) + '</span>' },
      { label: 'Total', key: 'total_estimate', sortable: true, cell: e => '<span class="mono"><b>' + UI.money(e.total_estimate) + '</b></span>' },
      { label: 'Days', cell: e => e.repair_days },
      { label: 'Status', key: 'status', sortable: true, cell: e => UI.badge(e.status, { pending: 'Awaiting decision', approved: 'Approved', rejected: 'Sent back' }) },
      { label: '', cell: e => e.status === 'pending'
          ? '<div class="btnrow"><button class="btn btn--sm btn--primary" data-ok="' + e.id + '">Approve</button>' +
            '<button class="btn btn--sm" data-back="' + e.id + '">Send back</button></div>'
          : '<span class="small muted">Closed</span>' }
    ],
    emptyTitle: 'No estimates on your claims',
    afterDraw(host) {
      host.querySelectorAll('[data-ok]').forEach(b => b.onclick = () => {
        const e = AVIC.estimates.find(x => x.id === +b.dataset.ok);
        UI.confirm('Approve this estimate?', 'The garage will be told to start work at ' + UI.moneyPlain(e.total_estimate) + '.',
          () => { e.status = 'approved'; Adjuster.estimates(s); UI.toast('Estimate approved.', 'ok'); }, 'Approve estimate');
      });
      host.querySelectorAll('[data-back]').forEach(b => b.onclick = () => {
        const e = AVIC.estimates.find(x => x.id === +b.dataset.back);
        UI.modal({
          title: 'Send the estimate back',
          body: '<form><div class="field"><label for="why">What should the garage change?</label>' +
                '<textarea id="why" name="why" placeholder="e.g. quote a reconditioned axle rather than a new one"></textarea></div></form>',
          confirm: 'Send back',
          onConfirm: d => { e.status = 'rejected'; e.adjuster_notes = d.why; Adjuster.estimates(s); UI.toast('Estimate returned to the garage.', 'ok'); }
        });
      });
    }
  });
};

/* ---------------- review panel ---------------- */
Adjuster.review = function (s) {
  const c = AVIC.claim(+UI.qs('id'));
  if (!AVIC.canSeeClaim(s, c)) {
    location.replace(AVIC.url('pages/errors/403.html?reason=not-assigned'));
    return;
  }
  const claimant = AVIC.user(c.user_id);
  const pol = AVIC.policy(c.policy_id);
  const docs = AVIC.docsFor(c.id);
  const est = AVIC.estimatesFor(c.id)[0];

  document.getElementById('c-number').textContent = c.claim_number;
  document.getElementById('c-sub').innerHTML = AVIC.labels.claim_type[c.claim_type] + ' · submitted ' + UI.date(c.submitted_at);
  document.getElementById('c-badge').innerHTML = UI.badge(c.status);
  document.getElementById('c-sla').innerHTML = UI.sla(c.due_date);

  document.getElementById('c-facts').innerHTML =
    '<dl class="kv">' +
      '<dt>Claimant</dt><dd>' + UI.esc(claimant.full_name) + ' · ' + UI.esc(claimant.phone) + '</dd>' +
      '<dt>Policy</dt><dd class="mono">' + UI.esc(pol.policy_number) + ' · ' + UI.esc(AVIC.labels.coverage[pol.coverage_type]) + '</dd>' +
      '<dt>Cover limit</dt><dd class="mono">' + UI.money(pol.coverage_limit) + '</dd>' +
      '<dt>Policy period</dt><dd>' + UI.date(pol.start_date) + ' to ' + UI.date(pol.end_date) + '</dd>' +
      '<dt>Vehicle</dt><dd>' + UI.esc([pol.vehicle_year, pol.vehicle_make, pol.vehicle_model].join(' ')) + ' · ' + UI.esc(pol.vehicle_plate) + '</dd>' +
      '<dt>Incident</dt><dd>' + UI.date(c.incident_date) + ' at ' + UI.esc(c.incident_location || 'location not given') + '</dd>' +
      '<dt>Police reference</dt><dd class="mono">' + UI.esc(c.police_report_ref || 'none') + '</dd>' +
      '<dt>Account</dt><dd>' + UI.esc(claimant.full_name) + ' has filed ' + AVIC.claims.filter(x => x.user_id === claimant.id).length + ' claims in total</dd>' +
      '<dt>What happened</dt><dd>' + UI.esc(c.incident_description) + '</dd>' +
    '</dl>';

  /* cover check — the plan flags this as a gap worth surfacing */
  const checks = [];
  const inPeriod = c.incident_date >= pol.start_date && c.incident_date <= pol.end_date;
  checks.push({ ok: inPeriod, t: inPeriod ? 'Incident falls inside the policy period' : 'Incident is outside the policy period' });
  const covered = !(pol.coverage_type === 'basic' && ['fire', 'theft', 'natural_disaster'].includes(c.claim_type));
  checks.push({ ok: covered, t: covered ? 'Peril is covered by the ' + AVIC.labels.coverage[pol.coverage_type].toLowerCase() + ' tier' : 'Peril is not covered by this tier' });
  const underLimit = (c.estimated_damage || 0) <= pol.coverage_limit;
  checks.push({ ok: underLimit, t: underLimit ? 'Claimed amount is within the cover limit' : 'Claimed amount exceeds the cover limit' });
  document.getElementById('c-checks').innerHTML = checks.map(k =>
    '<div class="notif"><div class="notif__bar" style="background:' + (k.ok ? 'var(--st-approved)' : 'var(--st-rejected)') + '"></div>' +
    '<div class="notif__m">' + UI.esc(k.t) + '</div></div>').join('');

  /* document viewer */
  let current = 0;
  function drawViewer() {
    const d = docs[current];
    const stage = document.getElementById('stage');
    if (!d) { stage.innerHTML = '<span>No documents attached</span>'; return; }
    stage.innerHTML = d.mime_type.startsWith('image/')
      ? '<div style="text-align:center;color:#63737f"><div style="font-size:44px">▣</div><div class="small">' + UI.esc(d.original_name) + '</div>' +
        '<div class="tiny">Image preview — files are streamed through the PHP proxy in the built system</div></div>'
      : '<div style="text-align:center;color:#63737f"><div style="font-size:44px">▤</div><div class="small">' + UI.esc(d.original_name) + '</div>' +
        '<div class="tiny">PDF viewer</div></div>';
    document.getElementById('v-name').textContent = d.original_name + ' · ' + UI.size(d.file_size);
    document.getElementById('v-verify').innerHTML = d.is_verified
      ? '<span class="badge badge--verified">Verified</span>'
      : '<button class="btn btn--sm btn--primary" id="do-verify">Mark verified</button>';
    const dv = document.getElementById('do-verify');
    if (dv) dv.onclick = () => {
      d.is_verified = 1; d.verified_by = s.id; d.verified_at = new Date().toISOString();
      drawDocs(); drawViewer(); UI.toast('Document marked verified.', 'ok');
    };
    document.querySelectorAll('.thumb').forEach((t, i) => t.classList.toggle('is-on', i === current));
  }
  function drawDocs() {
    document.getElementById('thumbs').innerHTML = docs.map((d, i) =>
      '<button class="thumb' + (i === current ? ' is-on' : '') + '" data-i="' + i + '" title="' + UI.esc(d.original_name) + '">' +
      (d.mime_type.startsWith('image/') ? '▣' : 'PDF') + '</button>').join('');
    document.querySelectorAll('.thumb').forEach(t => t.onclick = () => { current = +t.dataset.i; drawViewer(); });
    document.getElementById('doc-summary').textContent =
      docs.filter(d => d.is_verified).length + ' of ' + docs.length + ' documents verified';
  }
  drawDocs(); drawViewer();

  /* estimate side panel */
  document.getElementById('c-estimate').innerHTML = est
    ? '<dl class="kv"><dt>Garage</dt><dd>' + UI.esc(est.garage_name) + '</dd>' +
      '<dt>Parts / labour / other</dt><dd class="mono">' + UI.money(est.parts_cost, false) + ' / ' + UI.money(est.labor_cost, false) + ' / ' + UI.money(est.other_cost, false) + '</dd>' +
      '<dt>Total quoted</dt><dd class="mono"><b>' + UI.money(est.total_estimate) + '</b></dd>' +
      '<dt>Status</dt><dd>' + UI.badge(est.status, { pending: 'Awaiting decision', approved: 'Approved', rejected: 'Sent back' }) + '</dd></dl>'
    : '<p class="muted small">No garage estimate yet. Assign a garage to get one.</p>';

  /* decision form */
  const form = document.getElementById('decision');
  const amount = form.elements.recommended_amount;
  if (est) amount.value = est.total_estimate;
  document.getElementById('limit-note').innerHTML =
    'Cover limit on this policy is <span class="mono">' + UI.money(pol.coverage_limit) + '</span>.';

  amount.oninput = () => {
    const over = +amount.value > pol.coverage_limit;
    amount.closest('.field').classList.toggle('has-error', over);
    document.getElementById('amount-err').textContent = over ? 'Above the cover limit for this policy.' : '';
  };

  document.querySelectorAll('.choice[data-decision]').forEach(ch => ch.onclick = () => {
    document.querySelectorAll('.choice[data-decision]').forEach(x => x.classList.remove('is-on'));
    ch.classList.add('is-on');
    form.elements.decision.value = ch.dataset.decision;
    const needsAmount = ch.dataset.decision === 'approve';
    document.getElementById('amount-field').classList.toggle('hidden', !needsAmount);
  });

  form.onsubmit = e => {
    e.preventDefault();
    if (!form.elements.decision.value) return UI.toast('Choose a decision first.', 'bad');
    if (form.elements.decision.value === 'approve' && +amount.value > pol.coverage_limit)
      return UI.toast('The approved amount cannot exceed the cover limit.', 'bad');
    const words = { approve: 'Approve this claim?', reject: 'Reject this claim?', request_docs: 'Ask for more documents?', escalate: 'Escalate to an administrator?' };
    UI.confirm(words[form.elements.decision.value], 'The claimant is notified straight away and the decision is final.',
      () => {
        const map = { approve: 'approved', reject: 'rejected', request_docs: 'pending_docs', escalate: 'under_review' };
        c.status = map[form.elements.decision.value];
        if (form.elements.decision.value === 'approve') c.approved_amount = +amount.value;
        UI.toast('Decision recorded in the prototype.', 'ok');
        setTimeout(() => location.href = 'queue.html', 900);
      }, 'Record decision');
  };

  UI.counters(form);
};

AVIC.boot(function (s) {
  ({
    dashboard: Adjuster.dashboard,
    queue: Adjuster.queue,
    review: Adjuster.review,
    decided: Adjuster.decided,
    estimates: Adjuster.estimates,
    notifications: AVIC.pages.notifications
  }[document.body.dataset.page] || function () {})(s);
});
