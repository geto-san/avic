/* ============================================================
   AVIC — adjuster desk
   An adjuster sees only claims where adjuster_id matches their
   own id. Claims belonging to a colleague are not listed and
   cannot be opened by id.
   ============================================================ */

const Adjuster = {};
const OPEN_STATES = new Set(['submitted', 'under_review', 'pending_docs']);
const DECIDED_STATES = new Set(['approved', 'rejected', 'paid', 'closed']);

Adjuster.dashboard = function (s) {
  const mine = AVIC.claimsFor(s);
  const open = mine.filter(c => OPEN_STATES.has(c.status));
  const late = open.filter(c => c.due_date && new Date(c.due_date) < new Date());
  const decided = mine.filter(c => DECIDED_STATES.has(c.status));

  document.getElementById('k-open').textContent = open.length;
  document.getElementById('k-late').textContent = late.length;
  document.getElementById('k-decided').textContent = decided.length;
  document.getElementById('k-exposure').innerHTML = UI.money(open.reduce((t, c) => t + (c.estimated_damage || 0), 0));
  document.getElementById('greeting').textContent = 'Your desk, ' + s.name.split(' ')[0];

  UI.bars('#mix', Object.entries(
    mine.reduce((m, c) => {
      const k = AVIC.labels.status[c.status];
      m[k] = (m[k] || 0) + 1;
      return m;
    }, {})
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
  const rows = AVIC.claimsFor(s).filter(c => OPEN_STATES.has(c.status));
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
    rows: AVIC.claimsFor(s).filter(c => DECIDED_STATES.has(c.status)),
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
  const mineIds = new Set(AVIC.claimsFor(s).map(c => c.id));
  const rows = AVIC.estimates.filter(e => mineIds.has(e.claim_id));
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
        const id = +b.dataset.ok;
        UI.confirm('Approve this estimate?', 'The garage will be told to start work at ' + UI.moneyPlain(
          (AVIC.estimates.find(x => x.id === id) || {}).total_estimate) + '.',
          () => API.post('config/api/estimates-decision.php', { estimate_id: id, decision: 'approve' }).then(r => {
            if (!r.ok) return UI.toast((r.data && r.data.message) || 'Could not approve the estimate.', 'bad');
            AVIC.rerender(Adjuster.estimates);
            UI.toast('Estimate approved.', 'ok');
          }), 'Approve estimate');
      });
      host.querySelectorAll('[data-back]').forEach(b => b.onclick = () => {
        const id = +b.dataset.back;
        UI.modal({
          title: 'Send the estimate back',
          body: '<form><div class="field"><label for="why">What should the garage change?</label>' +
                '<textarea id="why" name="why" placeholder="e.g. quote a reconditioned axle rather than a new one"></textarea></div></form>',
          confirm: 'Send back',
          onConfirm: d => API.post('config/api/estimates-decision.php', {
            estimate_id: id, decision: 'send_back', notes: (d && d.why) || ''
          }).then(r => {
            if (!r.ok) return UI.toast((r.data && r.data.message) || 'Could not send the estimate back.', 'bad');
            AVIC.rerender(Adjuster.estimates);
            UI.toast('Estimate returned to the garage.', 'ok');
          })
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
    if (d.has_file) {
      const url = AVIC.docUrl(d);
      stage.innerHTML = d.mime_type.startsWith('image/')
        ? '<img src="' + url + '" alt="' + UI.esc(d.original_name) + '" class="doc-img">'
        : '<iframe src="' + url + '" class="doc-frame" title="' + UI.esc(d.original_name) + '"></iframe>';
    } else {
      stage.innerHTML = d.mime_type.startsWith('image/')
        ? '<div style="text-align:center;color:#63737f"><div style="font-size:44px">▣</div><div class="small">' + UI.esc(d.original_name) + '</div>' +
          '<div class="tiny">Seeded document — uploaded files preview here instead</div></div>'
        : '<div style="text-align:center;color:#63737f"><div style="font-size:44px">▤</div><div class="small">' + UI.esc(d.original_name) + '</div>' +
          '<div class="tiny">Seeded document</div></div>';
    }
    document.getElementById('v-name').textContent = d.original_name + ' · ' + UI.size(d.file_size);
    document.getElementById('v-verify').innerHTML = d.is_verified
      ? '<span class="badge badge--verified">Verified</span>'
      : '<button class="btn btn--sm btn--primary" id="do-verify">Mark verified</button>';
    const dv = document.getElementById('do-verify');
    if (dv) dv.onclick = () => {
      API.post('config/api/documents.php', { action: 'verify', id: d.id }).then(r => {
        if (!r.ok) return UI.toast((r.data && r.data.message) || 'Could not verify the document.', 'bad');
        UI.toast('Document marked verified.', 'ok');
        AVIC.rerender(Adjuster.review);
      });
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
  const wo = AVIC.workOrders.find(w => w.claim_id === c.id);
  let estHtml;
  if (est) {
    estHtml = '<dl class="kv"><dt>Garage</dt><dd>' + UI.esc(est.garage_name) + '</dd>' +
      '<dt>Parts / labour / other</dt><dd class="mono">' + UI.money(est.parts_cost, false) + ' / ' + UI.money(est.labor_cost, false) + ' / ' + UI.money(est.other_cost, false) + '</dd>' +
      '<dt>Total quoted</dt><dd class="mono"><b>' + UI.money(est.total_estimate) + '</b></dd>' +
      '<dt>Status</dt><dd>' + UI.badge(est.status, { pending: 'Awaiting decision', approved: 'Approved', rejected: 'Sent back' }) + '</dd></dl>';
  } else if (wo) {
    const g = AVIC.garages.find(x => x.id === wo.garage_user_id);
    estHtml = '<p class="muted small">Assigned to ' + UI.esc(g ? g.full_name : 'the garage') +
      ' · <b>' + UI.badge(wo.status, { open: 'awaiting quote', quoted: 'quote sent', revision_requested: 'revision asked' }) + '</b></p>';
  } else {
    estHtml = '<p class="muted small">No garage estimate yet. Assign a garage to get a quote.</p>' +
      '<div class="row2"><div class="field">' +
      '<select id="assign-garage" aria-label="Choose a garage">' +
        '<option value="">Choose a garage…</option>' +
        AVIC.garages.map(g => '<option value="' + g.id + '">' + UI.esc(g.full_name) + '</option>').join('') +
      '</select></div>' +
      '<div class="field"><button class="btn btn--primary" id="do-assign">Assign garage</button></div></div>';
  }
  document.getElementById('c-estimate').innerHTML = estHtml;
  /* payout: only the adjuster who approved (approved_by) may advance it.
     pending -> processing -> completed, one step at a time — the endpoint
     enforces the same single-step rule server-side. */
  const pay = AVIC.payoutFor(c.id);
  const payHost = document.getElementById('c-payout');
  if (pay && ['pending', 'processing'].includes(pay.status)) {
    const mine = (+pay.approved_by) === (+AVIC.session().id);
    const adv = pay.status === 'pending' ? 'mark_processing' : 'mark_paid';
    const advanceBtn = pay.status === 'pending' ? 'Mark processing' : 'Mark paid';
    const payoutActions = mine
      ? '<div class="btnrow"><button class="btn btn--sm btn--primary" id="p-advance">' + advanceBtn + '</button></div>'
      : '<p class="muted small">Only the adjuster who approved this claim can advance the payout.</p>';
    payHost.innerHTML =
      '<dl class="kv"><dt>Reference</dt><dd class="mono">' + UI.esc(pay.reference_number) + '</dd>' +
      '<dt>Amount</dt><dd class="mono">' + UI.money(pay.amount) + '</dd>' +
      '<dt>Method</dt><dd>' + UI.esc(AVIC.labels.payment_method[pay.payment_method] || pay.payment_method) + '</dd>' +
      '<dt>Status</dt><dd>' + UI.badge(pay.status, AVIC.labels.payout_status) + '</dd></dl>' +
      payoutActions;
    const pa = document.getElementById('p-advance');
    if (pa) pa.onclick = () => UI.confirm(
      pay.status === 'pending' ? 'Start processing this payout?' : 'Mark this payout as paid?',
      'Advancing it is written to the audit log and the claimant is notified.',
      () => API.post('config/api/payouts-update.php', {
        payout_id: pay.id, action: adv
      }).then(r => {
        if (!r.ok) return UI.toast((r.data && r.data.message) || 'Could not advance the payout.', 'bad');
        UI.toast('Payout advanced.', 'ok');
        AVIC.rerender(Adjuster.review);
      }), 'Advance payout');
  } else if (pay) {
    payHost.innerHTML =
      '<dl class="kv"><dt>Reference</dt><dd class="mono">' + UI.esc(pay.reference_number) + '</dd>' +
      '<dt>Amount</dt><dd class="mono">' + UI.money(pay.amount) + '</dd>' +
      '<dt>Method</dt><dd>' + UI.esc(AVIC.labels.payment_method[pay.payment_method] || pay.payment_method) + '</dd>' +
      '<dt>Status</dt><dd>' + UI.badge(pay.status, AVIC.labels.payout_status) + '</dd></dl>';
  } else {
    payHost.innerHTML = '<p class="muted small">A payout is created when a claim is approved.</p>';
  }

  const doAssign = document.getElementById('do-assign');
  if (doAssign) doAssign.onclick = () => {
    const gid = +document.getElementById('assign-garage').value;
    if (!gid) return UI.toast('Pick a garage first.', 'bad');
    API.post('config/api/claims-assign.php', { claim_id: c.id, garage_id: gid }).then(r => {
      if (!r.ok) return UI.toast((r.data && r.data.message) || 'Could not assign the garage.', 'bad');
      UI.toast('Work order sent to the garage.', 'ok');
      AVIC.rerender(Adjuster.review);
    });
  };

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
    const words = { approve: 'Approve this claim?', reject: 'Reject this claim?', request_docs: 'Ask for more documents?' };
    UI.confirm(words[form.elements.decision.value], 'The claimant is notified straight away and the decision is written to the audit log.',
      () => {
        API.post('config/api/claims-decision.php', {
          claim_id: c.id,
          decision: form.elements.decision.value,
          amount: form.elements.decision.value === 'approve' ? +amount.value : null,
          review_notes: form.elements.review_notes.value
        }).then(r => {
          if (!r.ok) return UI.toast((r.data && r.data.message) || 'Could not record the decision.', 'bad');
          UI.toast('Decision recorded. The claimant has been notified.', 'ok');
          setTimeout(() => location.href = 'queue.html', 1000);
        });
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
