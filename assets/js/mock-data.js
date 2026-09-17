/* AVIC Portal — mock dataset.
   Mirrors the dev-plan schema tables so field names survive the port to PHP. */

window.AVIC = window.AVIC || {};

AVIC.roles = {
  claimant: { label: 'Claimant',      accent: '#0f6e64', dir: 'claimant' },
  adjuster: { label: 'Adjuster',      accent: '#2f4b9b', dir: 'adjuster' },
  garage:   { label: 'Garage',        accent: '#b4621a', dir: 'garage'   },
  admin:    { label: 'Administrator', accent: '#8c2138', dir: 'admin'    }
};

AVIC.users = [
  { id: 1, full_name: 'Ahumuza Doreen',  email: 'doreen@example.ug',    phone: '+256 772 114 902', role: 'claimant', status: 'active',  created_at: '2025-11-02', last_login: '2026-09-15 08:12' },
  { id: 2, full_name: 'Okot Brian',      email: 'brian.okot@avic.ug',   phone: '+256 701 553 118', role: 'adjuster', status: 'active',  created_at: '2025-06-14', last_login: '2026-09-16 07:40' },
  { id: 3, full_name: 'Kigongo Motors',  email: 'desk@kigongomotors.ug',phone: '+256 414 250 771', role: 'garage',   status: 'active',  created_at: '2025-08-21', last_login: '2026-09-15 16:55' },
  { id: 4, full_name: 'Nakato Sylvia',   email: 'sylvia@avic.ug',       phone: '+256 782 660 431', role: 'admin',    status: 'active',  created_at: '2025-01-09', last_login: '2026-09-16 06:58' },
  { id: 5, full_name: 'Mugisha Alex',    email: 'alex.m@example.ug',    phone: '+256 758 209 663', role: 'claimant', status: 'active',  created_at: '2026-02-17', last_login: '2026-09-14 19:02' },
  { id: 6, full_name: 'Namara Pride',    email: 'pride.n@example.ug',   phone: '+256 703 887 145', role: 'claimant', status: 'pending', created_at: '2026-09-12', last_login: null },
  { id: 7, full_name: 'Ssemwanga Autoworks', email: 'info@ssemwanga.ug',phone: '+256 392 110 448', role: 'garage',   status: 'pending', created_at: '2026-09-10', last_login: null },
  { id: 8, full_name: 'Atuhaire Grace',  email: 'grace.a@avic.ug',      phone: '+256 772 448 210', role: 'adjuster', status: 'active',  created_at: '2025-09-30', last_login: '2026-09-16 08:05' },
  { id: 9, full_name: 'Kato Ronald',     email: 'r.kato@example.ug',    phone: '+256 706 331 992', role: 'claimant', status: 'suspended', created_at: '2025-12-04', last_login: '2026-07-22 11:30' }
];

AVIC.policies = [
  { id: 1, user_id: 1, policy_number: 'POL-UG-88421', vehicle_make: 'Toyota',   vehicle_model: 'Premio',     vehicle_year: 2016, vehicle_vin: 'JTD1234567890ABCD', vehicle_plate: 'UBG 442H', coverage_type: 'comprehensive', coverage_limit: 42000000, premium: 1850000, start_date: '2026-01-15', end_date: '2027-01-14', status: 'active' },
  { id: 2, user_id: 1, policy_number: 'POL-UG-90137', vehicle_make: 'Nissan',   vehicle_model: 'X-Trail',    vehicle_year: 2013, vehicle_vin: 'JN8AS5MT0DW123456', vehicle_plate: 'UAX 019Z', coverage_type: 'third_party',   coverage_limit: 12000000, premium: 640000,  start_date: '2025-09-01', end_date: '2026-08-31', status: 'expired' },
  { id: 3, user_id: 5, policy_number: 'POL-UG-77310', vehicle_make: 'Toyota',   vehicle_model: 'Hiace',      vehicle_year: 2011, vehicle_vin: 'JTFR1234567891XYZ', vehicle_plate: 'UBB 771K', coverage_type: 'comprehensive', coverage_limit: 30000000, premium: 1420000, start_date: '2026-03-01', end_date: '2027-02-28', status: 'active' },
  { id: 4, user_id: 9, policy_number: 'POL-UG-65002', vehicle_make: 'Subaru',   vehicle_model: 'Forester',   vehicle_year: 2014, vehicle_vin: 'JF2SJ6DC0EH123987', vehicle_plate: 'UAP 650C', coverage_type: 'basic',         coverage_limit: 18000000, premium: 890000,  start_date: '2026-04-20', end_date: '2027-04-19', status: 'active' }
];

/* claims — status set matches the schema ENUM exactly */
AVIC.claims = [
  { id: 101, claim_number: 'CLM-2026-00042', user_id: 1, policy_id: 1, adjuster_id: 2, garage_id: 3,
    incident_date: '2026-09-04', incident_location: 'Jinja Road roundabout, Kampala',
    incident_description: 'Rear-ended while stationary at the traffic lights. Bumper, tail lights and boot lid damaged. The other driver stopped and police were called to the scene.',
    claim_type: 'collision', estimated_damage: 6850000, approved_amount: null, police_report_ref: 'CPS/KLA/2026/1184',
    status: 'under_review', submitted_at: '2026-09-05 09:22', reviewed_at: null, resolved_at: null,
    created_at: '2026-09-04 18:40', due_date: '2026-09-19' },

  { id: 102, claim_number: 'CLM-2026-00038', user_id: 1, policy_id: 1, adjuster_id: 2, garage_id: null,
    incident_date: '2026-07-19', incident_location: 'Entebbe Road, Kajjansi',
    incident_description: 'Windscreen shattered by stone thrown up from a truck. No other vehicle involved.',
    claim_type: 'other', estimated_damage: 1200000, approved_amount: 1100000, police_report_ref: null,
    status: 'paid', submitted_at: '2026-07-19 15:10', reviewed_at: '2026-07-22 11:02', resolved_at: '2026-07-28 10:15',
    created_at: '2026-07-19 14:50', due_date: '2026-08-02' },

  { id: 103, claim_number: 'CLM-2026-00051', user_id: 1, policy_id: 1, adjuster_id: null, garage_id: null,
    incident_date: '2026-09-14', incident_location: '',
    incident_description: 'Side mirror and left door scraped in the car park.',
    claim_type: 'vandalism', estimated_damage: null, approved_amount: null, police_report_ref: null,
    status: 'draft', submitted_at: null, reviewed_at: null, resolved_at: null,
    created_at: '2026-09-14 20:05', due_date: null },

  { id: 104, claim_number: 'CLM-2026-00047', user_id: 5, policy_id: 3, adjuster_id: 2, garage_id: 3,
    incident_date: '2026-08-28', incident_location: 'Masaka–Mbarara highway, Lyantonde',
    incident_description: 'Head-on side swipe with a lorry overtaking on a bend. Passenger side panels and front axle affected.',
    claim_type: 'collision', estimated_damage: 14300000, approved_amount: null, police_report_ref: 'LYT/2026/0442',
    status: 'pending_docs', submitted_at: '2026-08-29 08:05', reviewed_at: '2026-09-02 14:20', resolved_at: null,
    created_at: '2026-08-28 21:15', due_date: '2026-09-12' },

  { id: 105, claim_number: 'CLM-2026-00049', user_id: 5, policy_id: 3, adjuster_id: 8, garage_id: null,
    incident_date: '2026-09-09', incident_location: 'Nakawa market parking, Kampala',
    incident_description: 'Vehicle stolen from the market parking between 14:00 and 16:00. Reported at Nakawa Police Station the same evening.',
    claim_type: 'theft', estimated_damage: 28000000, approved_amount: null, police_report_ref: 'NKW/2026/3390',
    status: 'submitted', submitted_at: '2026-09-10 07:44', reviewed_at: null, resolved_at: null,
    created_at: '2026-09-09 19:30', due_date: '2026-09-24' },

  { id: 106, claim_number: 'CLM-2026-00044', user_id: 9, policy_id: 4, adjuster_id: 2, garage_id: null,
    incident_date: '2026-08-11', incident_location: 'Gulu town, Pece division',
    incident_description: 'Bonnet and engine bay fire after a short circuit while parked overnight.',
    claim_type: 'fire', estimated_damage: 9600000, approved_amount: 0, police_report_ref: 'GLU/2026/0771',
    status: 'rejected', submitted_at: '2026-08-12 10:02', reviewed_at: '2026-08-18 16:40', resolved_at: '2026-08-18 16:40',
    created_at: '2026-08-11 23:12', due_date: '2026-08-26',
    rejection_reason: 'Policy POL-UG-65002 is a basic cover tier; fire damage is not a covered peril under this tier.' },

  { id: 107, claim_number: 'CLM-2026-00040', user_id: 5, policy_id: 3, adjuster_id: 8, garage_id: 3,
    incident_date: '2026-07-30', incident_location: 'Bweyogerere, Wakiso',
    incident_description: 'Flood water damage to the cabin and electricals after heavy rain.',
    claim_type: 'natural_disaster', estimated_damage: 5200000, approved_amount: 4800000, police_report_ref: null,
    status: 'approved', submitted_at: '2026-07-31 09:00', reviewed_at: '2026-08-05 13:15', resolved_at: null,
    created_at: '2026-07-30 22:41', due_date: '2026-08-14' },

  { id: 108, claim_number: 'CLM-2026-00053', user_id: 1, policy_id: 1, adjuster_id: 2, garage_id: null,
    incident_date: '2026-08-22', incident_location: 'Kireka stage, Wakiso',
    incident_description: 'Left wing mirror and door skin damaged by a boda boda squeezing past in traffic.',
    claim_type: 'collision', estimated_damage: 2400000, approved_amount: 2150000, police_report_ref: null,
    status: 'approved', submitted_at: '2026-08-23 08:15', reviewed_at: '2026-08-30 11:40', resolved_at: null,
    created_at: '2026-08-22 18:05', due_date: '2026-09-06' }
];

AVIC.documents = [
  { id: 1, claim_id: 101, uploaded_by: 1, doc_type: 'accident_photo', original_name: 'rear-bumper.jpg',   file_size: 2415000, mime_type: 'image/jpeg',      is_verified: 1, verified_by: 2, verified_at: '2026-09-06 10:10', uploaded_at: '2026-09-04 18:44' },
  { id: 2, claim_id: 101, uploaded_by: 1, doc_type: 'accident_photo', original_name: 'tail-light.jpg',    file_size: 1880000, mime_type: 'image/jpeg',      is_verified: 1, verified_by: 2, verified_at: '2026-09-06 10:11', uploaded_at: '2026-09-04 18:45' },
  { id: 3, claim_id: 101, uploaded_by: 1, doc_type: 'police_report',  original_name: 'police-report.pdf', file_size: 640000,  mime_type: 'application/pdf', is_verified: 0, verified_by: null, verified_at: null, uploaded_at: '2026-09-05 08:50' },
  { id: 4, claim_id: 101, uploaded_by: 3, doc_type: 'repair_estimate',original_name: 'kigongo-quote.pdf', file_size: 310000,  mime_type: 'application/pdf', is_verified: 0, verified_by: null, verified_at: null, uploaded_at: '2026-09-08 11:02' },
  { id: 5, claim_id: 104, uploaded_by: 5, doc_type: 'accident_photo', original_name: 'side-panel.jpg',    file_size: 3120000, mime_type: 'image/jpeg',      is_verified: 1, verified_by: 2, verified_at: '2026-09-02 14:05', uploaded_at: '2026-08-28 21:20' },
  { id: 6, claim_id: 104, uploaded_by: 5, doc_type: 'police_report',  original_name: 'lyantonde-pr.pdf',  file_size: 720000,  mime_type: 'application/pdf', is_verified: 0, verified_by: null, verified_at: null, uploaded_at: '2026-08-29 07:55' },
  { id: 7, claim_id: 102, uploaded_by: 1, doc_type: 'accident_photo', original_name: 'windscreen.jpg',    file_size: 1450000, mime_type: 'image/jpeg',      is_verified: 1, verified_by: 2, verified_at: '2026-07-22 10:40', uploaded_at: '2026-07-19 15:02' },
  { id: 8, claim_id: 105, uploaded_by: 5, doc_type: 'police_report',  original_name: 'nakawa-theft.pdf',  file_size: 505000,  mime_type: 'application/pdf', is_verified: 0, verified_by: null, verified_at: null, uploaded_at: '2026-09-10 07:40' },
  { id: 10, claim_id: 108, uploaded_by: 1, doc_type: 'accident_photo', original_name: 'wing-mirror.jpg',  file_size: 980000,  mime_type: 'image/jpeg',      is_verified: 1, verified_by: 2, verified_at: '2026-08-30 11:20', uploaded_at: '2026-08-22 18:10' },
  { id: 9, claim_id: 107, uploaded_by: 5, doc_type: 'vehicle_photo',  original_name: 'cabin-water.jpg',   file_size: 2210000, mime_type: 'image/jpeg',      is_verified: 1, verified_by: 8, verified_at: '2026-08-05 12:50', uploaded_at: '2026-07-30 22:50' }
];

AVIC.estimates = [
  { id: 1, claim_id: 101, garage_user_id: 3, garage_name: 'Kigongo Motors', garage_phone: '+256 414 250 771', garage_address: 'Plot 14, Ntinda Industrial Area',
    parts_cost: 4200000, labor_cost: 2100000, other_cost: 550000, total_estimate: 6850000, repair_days: 9, status: 'pending', adjuster_notes: null, created_at: '2026-09-08 11:00' },
  { id: 2, claim_id: 107, garage_user_id: 3, garage_name: 'Kigongo Motors', garage_phone: '+256 414 250 771', garage_address: 'Plot 14, Ntinda Industrial Area',
    parts_cost: 3100000, labor_cost: 1750000, other_cost: 350000, total_estimate: 5200000, repair_days: 6, status: 'approved', adjuster_notes: 'Electrical harness replacement reduced to a repair. Approved at UGX 4,800,000.', created_at: '2026-08-02 09:15' },
  { id: 3, claim_id: 104, garage_user_id: 3, garage_name: 'Kigongo Motors', garage_phone: '+256 414 250 771', garage_address: 'Plot 14, Ntinda Industrial Area',
    parts_cost: 9800000, labor_cost: 3900000, other_cost: 600000, total_estimate: 14300000, repair_days: 21, status: 'rejected', adjuster_notes: 'Front axle assembly quoted at new-part price; a reconditioned unit is acceptable. Please resubmit.', created_at: '2026-09-01 10:30' }
];

/* garage work orders — claims assigned to a garage for quoting */
AVIC.workOrders = [
  { id: 1, claim_id: 101, garage_user_id: 3, assigned_by: 2, assigned_at: '2026-09-06 12:00', due: '2026-09-13', status: 'quoted' },
  { id: 2, claim_id: 104, garage_user_id: 3, assigned_by: 2, assigned_at: '2026-08-30 09:10', due: '2026-09-06', status: 'revision_requested' },
  { id: 3, claim_id: 107, garage_user_id: 3, assigned_by: 8, assigned_at: '2026-08-01 08:00', due: '2026-08-08', status: 'closed' }
];

AVIC.payouts = [
  { id: 1, claim_id: 102, user_id: 1, approved_by: 4, amount: 1100000, payment_method: 'mobile_money', account_name: 'Ahumuza Doreen', account_number: '+256 772 114 902', bank_name: null,
    reference_number: 'PAY-20260728-00113', status: 'completed', processed_at: '2026-07-28 10:15', created_at: '2026-07-26 09:40' },
  { id: 2, claim_id: 107, user_id: 5, approved_by: 4, amount: 4800000, payment_method: 'bank_transfer', account_name: 'Mugisha Alex', account_number: '01620****9', bank_name: 'Stanbic Bank Uganda',
    reference_number: 'PAY-20260906-00121', status: 'processing', processed_at: null, created_at: '2026-09-06 15:20' }
];

AVIC.notifications = [
  { id: 1, user_id: 1, claim_id: 101, type: 'doc_verified',   title: 'Two photos verified',        message: 'Okot Brian verified the rear bumper and tail light photos on CLM-2026-00042.', is_read: 0, created_at: '2026-09-06 10:12' },
  { id: 2, user_id: 1, claim_id: 101, type: 'status_changed', title: 'Claim moved to under review', message: 'CLM-2026-00042 is now with an adjuster. Expect a decision by 19 Sep.',        is_read: 0, created_at: '2026-09-05 09:30' },
  { id: 3, user_id: 1, claim_id: 102, type: 'payout',         title: 'Payout completed',            message: 'UGX 1,100,000 sent to your mobile money line. Reference PAY-20260728-00113.',   is_read: 1, created_at: '2026-07-28 10:16' },
  { id: 4, user_id: 2, claim_id: 105, type: 'claim_assigned', title: 'New claim in your queue',     message: 'CLM-2026-00049 (theft, UGX 28,000,000) was routed to you.',                     is_read: 0, created_at: '2026-09-10 07:45' },
  { id: 5, user_id: 2, claim_id: 101, type: 'estimate',       title: 'Garage estimate submitted',   message: 'Kigongo Motors quoted UGX 6,850,000 on CLM-2026-00042.',                        is_read: 0, created_at: '2026-09-08 11:05' },
  { id: 6, user_id: 3, claim_id: 104, type: 'estimate',       title: 'Revision requested',          message: 'Adjuster asked for a reconditioned-part quote on CLM-2026-00047.',               is_read: 0, created_at: '2026-09-01 16:40' },
  { id: 7, user_id: 3, claim_id: 101, type: 'work_order',     title: 'Work order assigned',         message: 'You have been assigned CLM-2026-00042. Quote due 13 Sep.',                       is_read: 1, created_at: '2026-09-06 12:01' },
  { id: 8, user_id: 4, claim_id: 107, type: 'payout',         title: 'Payout awaiting release',     message: 'CLM-2026-00040 was approved at UGX 4,800,000 and is ready for release.',         is_read: 0, created_at: '2026-09-06 15:22' },
  { id: 9, user_id: 4, claim_id: null, type: 'account',       title: 'Two accounts await approval', message: 'Namara Pride and Ssemwanga Autoworks registered and need review.',               is_read: 0, created_at: '2026-09-12 08:30' }
];

AVIC.settings = {
  company_name: 'AVIC Insurance Co.',
  payout_currency: 'UGX',
  max_upload_mb: 10,
  claims_sla_days: 14,
  auto_assign: 'off',
  support_email: 'support@avic.ug'
};

/* ------------------------------------------------------------
   Lookups + role-scoped readers.
   Every list a page renders goes through one of these, so the
   visibility rules live in one place and are easy to port to
   the PHP models later.
   ------------------------------------------------------------ */
AVIC.user   = id => AVIC.users.find(u => u.id === id) || null;
AVIC.policy = id => AVIC.policies.find(p => p.id === id) || null;
AVIC.claim  = id => AVIC.claims.find(c => c.id === id) || null;
AVIC.docsFor = claimId => AVIC.documents.filter(d => d.claim_id === claimId);
AVIC.estimatesFor = claimId => AVIC.estimates.filter(e => e.claim_id === claimId);
AVIC.payoutFor = claimId => AVIC.payouts.find(p => p.claim_id === claimId) || null;

AVIC.claimsFor = function (session) {
  if (!session) return [];
  switch (session.role) {
    case 'claimant': return AVIC.claims.filter(c => c.user_id === session.id);
    case 'adjuster': return AVIC.claims.filter(c => c.adjuster_id === session.id);
    case 'garage':   return AVIC.workOrders.filter(w => w.garage_user_id === session.id)
                              .map(w => AVIC.claim(w.claim_id)).filter(Boolean);
    case 'admin':    return AVIC.claims.slice();
    default:         return [];
  }
};

AVIC.notificationsFor = session =>
  session ? AVIC.notifications.filter(n => n.user_id === session.id) : [];

AVIC.policiesFor = session =>
  session && session.role === 'claimant'
    ? AVIC.policies.filter(p => p.user_id === session.id)
    : (session && session.role === 'admin' ? AVIC.policies.slice() : []);

AVIC.workOrdersFor = session =>
  session && session.role === 'garage'
    ? AVIC.workOrders.filter(w => w.garage_user_id === session.id)
    : [];

/* Can this session open this claim at all? Mirrors the role matrix
   in section 06 of the plan. The UI calls this before rendering a
   claim page and shows the 403 screen when it returns false. */
AVIC.canSeeClaim = function (session, claim) {
  if (!session || !claim) return false;
  if (session.role === 'admin') return true;
  if (session.role === 'claimant') return claim.user_id === session.id;
  if (session.role === 'adjuster') return claim.adjuster_id === session.id;
  if (session.role === 'garage')   return AVIC.workOrders.some(w => w.garage_user_id === session.id && w.claim_id === claim.id);
  return false;
};

/* Fields a garage is allowed to read — vehicle and damage only,
   never the claimant's identity or payout details. */
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
