/* ============================================================
   AVIC — data layer
   These arrays used to be a hardcoded prototype dataset. They are now
   empty containers that assets/js/api.js fills from the server
   (config/api/bootstrap.php) for the signed-in user. Every list a page
   renders still goes through the readers below, so the role visibility
   rules live in one place and match the server's own checks.
   ============================================================ */

window.AVIC = window.AVIC || {};

AVIC.roles = {
  claimant: { label: 'Claimant',  desk: 'Claimant workspace',  accent: '#0f6e64', home: 'dashboard.html', dir: 'claimant' },
  adjuster: { label: 'Adjuster',  desk: 'Adjuster desk',       accent: '#2f4b9b', home: 'dashboard.html', dir: 'adjuster' },
  garage:   { label: 'Garage',    desk: 'Garage bay',          accent: '#b4621a', home: 'dashboard.html', dir: 'garage' }
};

/* ---- hydrated by the API (see api.js) ---- */
AVIC.users         = [];
AVIC.policies      = [];
AVIC.claims        = [];
AVIC.documents     = [];
AVIC.estimates     = [];
AVIC.workOrders    = [];
AVIC.payouts       = [];
AVIC.notifications = [];
AVIC.garages       = [];
AVIC.settings      = {
  company_name: 'AVIC Insurance Co.',
  payout_currency: 'UGX',
  max_upload_mb: 10,
  claims_sla_days: 14,
  auto_assign: 'round_robin',
  support_email: 'support@avic.ug'
};

/* ------------------------------------------------------------
   Lookups + role-scoped readers. These mirror (and now shadow) the
   server-side visibility checks; a well-behaved UI never renders data
   the server did not send for this session.
   ------------------------------------------------------------ */
AVIC.user   = id => AVIC.users.find(u => u.id === id) || null;
AVIC.policy = id => AVIC.policies.find(p => p.id === id) || null;
AVIC.claim  = id => AVIC.claims.find(c => c.id === id) || null;
AVIC.claimByNumber = n => AVIC.claims.find(c => c.claim_number === n) || null;
AVIC.docsFor = claimId => AVIC.documents.filter(d => d.claim_id === claimId);
AVIC.estimatesFor = claimId => AVIC.estimates.filter(e => e.claim_id === claimId);
AVIC.payoutFor = claimId => AVIC.payouts.find(p => p.claim_id === claimId) || null;

/* streamable URL for a document row (served through the PHP proxy) */
AVIC.docUrl = function (doc) {
  return AVIC.url('config/uploads.php?doc=' + doc.id);
};

AVIC.claimsFor = function (session) {
  if (!session) return [];
  switch (session.role) {
    case 'claimant': return AVIC.claims.filter(c => c.user_id === session.id);
    case 'adjuster': return AVIC.claims.filter(c => c.adjuster_id === session.id);
    case 'garage':   return AVIC.workOrders.filter(w => w.garage_user_id === session.id)
                              .map(w => AVIC.claim(w.claim_id)).filter(Boolean);
    default:         return [];
  }
};

AVIC.notificationsFor = session =>
  session ? AVIC.notifications.filter(n => n.user_id === session.id) : [];

AVIC.policiesFor = session =>
  session && session.role === 'claimant'
    ? AVIC.policies.filter(p => p.user_id === session.id)
    : [];

AVIC.workOrdersFor = session =>
  session && session.role === 'garage'
    ? AVIC.workOrders.filter(w => w.garage_user_id === session.id)
    : [];

/* Can this session open this claim at all? Mirrors the role matrix. */
AVIC.canSeeClaim = function (session, claim) {
  if (!session || !claim) return false;
  if (session.role === 'claimant') return claim.user_id === session.id;
  if (session.role === 'adjuster') return claim.adjuster_id === session.id;
  if (session.role === 'garage')   return AVIC.workOrders.some(w => w.garage_user_id === session.id && w.claim_id === claim.id);
  return false;
};

/* Fields a garage is allowed to read — vehicle and damage only. */
AVIC.garageViewOf = function (claim) {
  const pol = AVIC.policy(claim.policy_id) || {};
  return {
    claim_number: claim.claim_number,
    vehicle: [pol.vehicle_year, pol.vehicle_make, pol.vehicle_model].filter(Boolean).join(' '),
    plate: pol.vehicle_plate,
    claim_type: claim.claim_type,
    incident_date: claim.incident_date,
    damage_notes: claim.incident_description
  };
};

AVIC.labels = {
  status: {
    draft: 'Draft', submitted: 'Submitted', under_review: 'Under review',
    pending_docs: 'Documents needed', approved: 'Approved', rejected: 'Rejected',
    paid: 'Paid', closed: 'Closed'
  },
  claim_type: {
    collision: 'Collision', theft: 'Theft', vandalism: 'Vandalism',
    fire: 'Fire', natural_disaster: 'Natural disaster', other: 'Other'
  },
  doc_type: {
    accident_photo: 'Accident photo', police_report: 'Police report',
    repair_estimate: 'Repair estimate', medical_report: 'Medical report',
    vehicle_photo: 'Vehicle photo', other: 'Other'
  },
  coverage: { basic: 'Basic', comprehensive: 'Comprehensive', third_party: 'Third party' },
  payment_method: { bank_transfer: 'Bank transfer', cheque: 'Cheque', mobile_money: 'Mobile money' },
  payout_status: { pending: 'Pending', processing: 'Processing', completed: 'Completed', failed: 'Failed' }
};

/* the claimant-facing status ladder used by the timeline */
AVIC.statusLadder = ['draft', 'submitted', 'under_review', 'approved', 'paid'];