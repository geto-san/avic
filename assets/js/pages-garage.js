/* AVIC Portal — garage bay. Only claims with a work order for
   this garage, and only via the AVIC.garageViewOf() projection. */

const Garage = {};

Garage.dashboard = function (s) {
  const orders = AVIC.workOrdersFor(s);
  const open = orders.filter(o => o.status !== 'closed');
  const mine = AVIC.estimates.filter(e => e.garage_user_id === s.id);

  document.getElementById('k-open').textContent = open.length;
  document.getElementById('k-pending').textContent = mine.filter(e => e.status === 'pending').length;
  document.getElementById('k-approved').textContent = mine.filter(e => e.status === 'approved').length;
  document.getElementById('k-value').innerHTML = UI.money(mine.filter(e => e.status === 'approved').reduce((t, e) => t + e.total_estimate, 0));
  document.getElementById('greeting').textContent = s.name;

  UI.table('#orders', {
    rows: open,
    cols: [
      { label: 'Claim', cell: o => '<a class="link mono" href="estimate-new.html?claim=' + o.claim_id + '">' + AVIC.claim(o.claim_id).claim_number + '</a>' },
      { label: 'Vehicle', cell: o => UI.esc(AVIC.garageViewOf(AVIC.claim(o.claim_id)).vehicle) },
      { label: 'Damage', cell: o => UI.esc(AVIC.labels.claim_type[AVIC.claim(o.claim_id).claim_type]) },
      { label: 'Quote due', cell: o => UI.sla(o.due) },
      { label: 'State', cell: o => UI.badge(o.status === 'quoted' ? 'submitted' : 'pending_docs',
          { submitted: 'Quote sent', pending_docs: 'Revision asked for' }) }
    ],
    emptyTitle: 'No work orders open',
    emptyBody: 'An adjuster assigns vehicles to your workshop from the claim review screen.'
  });
};

Garage.workOrders = function (s) {
  const rows = AVIC.workOrdersFor(s);
  let st = '';
  const t = UI.table('#wo-table', {
    rows,
    filter: o => !st || o.status === st,
    cols: [
      { label: 'Claim', cell: o => '<span class="mono">' + AVIC.claim(o.claim_id).claim_number + '</span>' },
      { label: 'Vehicle', cell: o => UI.esc(AVIC.garageViewOf(AVIC.claim(o.claim_id)).vehicle) },
      { label: 'Plate', cell: o => '<span class="mono">' + UI.esc(AVIC.garageViewOf(AVIC.claim(o.claim_id)).plate) + '</span>' },
      { label: 'Damage type', cell: o => UI.esc(AVIC.labels.claim_type[AVIC.claim(o.claim_id).claim_type]) },
      { label: 'Assigned', cell: o => UI.date(o.assigned_at) },
      { label: 'Quote due', cell: o => UI.sla(o.due) },
      { label: '', cell: o => o.status === 'closed'
          ? '<span class="small muted">Closed</span>'
          : '<a class="btn btn--sm btn--primary" href="estimate-new.html?claim=' + o.claim_id + '">' +
            (o.status === 'quoted' ? 'Revise quote' : 'Quote this job') + '</a>' }
    ],
    emptyTitle: 'Nothing assigned to your workshop'
  });
  document.getElementById('f-state').onchange = e => { st = e.target.value; t.redraw(); };
};

Garage.estimates = function (s) {
  UI.table('#est-table', {
    rows: AVIC.estimates.filter(e => e.garage_user_id === s.id),
    sortKey: 'created_at',
    cols: [
      { label: 'Claim', cell: e => '<span class="mono">' + AVIC.claim(e.claim_id).claim_number + '</span>' },
      { label: 'Submitted', key: 'created_at', sortable: true, cell: e => UI.date(e.created_at) },
      { label: 'Parts', cell: e => '<span class="mono">' + UI.money(e.parts_cost) + '</span>' },
      { label: 'Labour', cell: e => '<span class="mono">' + UI.money(e.labor_cost) + '</span>' },
      { label: 'Total', key: 'total_estimate', sortable: true, cell: e => '<span class="mono"><b>' + UI.money(e.total_estimate) + '</b></span>' },
      { label: 'Days', cell: e => e.repair_days },
      { label: 'Outcome', key: 'status', sortable: true, cell: e => UI.badge(e.status, { pending: 'With the adjuster', approved: 'Approved', rejected: 'Revision asked for' }) }
    ],
    afterDraw(host, view) {
      const returned = view.filter(e => e.status === 'rejected' && e.adjuster_notes);
      document.getElementById('notes').innerHTML = returned.length
        ? returned.map(e => '<div class="note note--warn"><b class="mono">' + AVIC.claim(e.claim_id).claim_number + '</b> — ' + UI.esc(e.adjuster_notes) + '</div>').join('')
        : '';
    },
    emptyTitle: 'No estimates submitted yet'
  });
};

Garage.estimateForm = function (s) {
  const claimId = +UI.qs('claim');
  const claim = AVIC.claim(claimId);
  if (!AVIC.canSeeClaim(s, claim)) {
    location.replace(AVIC.url('pages/errors/403.html?reason=no-work-order'));
    return;
  }
  const view = AVIC.garageViewOf(claim);      // limited projection, by design
  const existing = AVIC.estimates.find(e => e.claim_id === claimId && e.garage_user_id === s.id);

  document.getElementById('c-number').textContent = view.claim_number;
  document.getElementById('c-sub').textContent = view.vehicle + ' · ' + view.plate;
  document.getElementById('c-vehicle').innerHTML =
    '<dl class="kv">' +
      '<dt>Vehicle</dt><dd>' + UI.esc(view.vehicle) + '</dd>' +
      '<dt>Number plate</dt><dd class="mono">' + UI.esc(view.plate) + '</dd>' +
      '<dt>Damage type</dt><dd>' + UI.esc(AVIC.labels.claim_type[view.claim_type]) + '</dd>' +
      '<dt>Incident date</dt><dd>' + UI.date(view.incident_date) + '</dd>' +
      '<dt>Reported damage</dt><dd>' + UI.esc(view.damage_notes) + '</dd>' +
    '</dl>' +
    '<div class="note">Owner details, policy numbers and settlement amounts stay with the adjuster. Quote from the vehicle and the damage description.</div>';

  const form = document.getElementById('estimate-form');
  if (existing) {
    form.elements.parts_cost.value = existing.parts_cost;
    form.elements.labor_cost.value = existing.labor_cost;
    form.elements.other_cost.value = existing.other_cost;
    form.elements.repair_days.value = existing.repair_days;
    if (existing.adjuster_notes) {
      document.getElementById('adj-note').innerHTML =
        '<div class="note note--warn"><b>The adjuster asked for changes:</b><br>' + UI.esc(existing.adjuster_notes) + '</div>';
    }
  }

  function total() {
    const t = (+form.elements.parts_cost.value || 0) + (+form.elements.labor_cost.value || 0) + (+form.elements.other_cost.value || 0);
    document.getElementById('est-total').innerHTML = UI.money(t);
    return t;
  }
  ['parts_cost', 'labor_cost', 'other_cost'].forEach(n => form.elements[n].oninput = total);
  total();

  UI.dropzone('#dropzone', '#filelist');
  UI.counters(form);

  form.onsubmit = e => {
    e.preventDefault();
    if (!UI.validate(form)) return UI.toast('Fill in the highlighted fields.', 'bad');
    if (total() <= 0) return UI.toast('The quote total cannot be zero.', 'bad');
    UI.confirm('Send this quote to the adjuster?',
      'Total ' + UI.moneyPlain(total()) + ' over ' + (form.elements.repair_days.value || '—') + ' working days.',
      () => {
        UI.toast('Quote sent in the prototype — nothing was saved.', 'ok');
        setTimeout(() => location.href = 'estimates.html', 900);
      }, 'Send quote');
  };
};

AVIC.boot(function (s) {
  ({
    dashboard: Garage.dashboard,
    'work-orders': Garage.workOrders,
    estimates: Garage.estimates,
    'estimate-new': Garage.estimateForm,
    notifications: AVIC.pages.notifications,
    profile: AVIC.pages.profile
  }[document.body.dataset.page] || function () {})(s);
});
