<?php
declare(strict_types=1);

/**
 * Seed the demo dataset so the whole portal has something real to show.
 *
 *   php config/db/seed-demo.php
 *
 * Idempotent — safe to run again (rows are upserted by primary key). It
 * loads the schema first (config/db/schema.php), creating the work_orders
 * table and anything else a fresh database is missing, then seeds:
 *
 *   - demo users (ids 1-9) with a single shared demo password
 *   - policies, claims, documents metadata, garage estimates, work orders,
 *     payouts, notifications and the settings rows
 *
 * Demo sign-in (any account below works):
 *   email of your choice from the list   password: Demo2026!
 *
 * The legacy document rows are metadata-only (no file on disk); documents
 * uploaded through the portal are real files served from /config/uploads.php.
 */

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/schema.php';

// Demo-only sign-in secret, deliberately public for the prototype (see README
// "Demo accounts"). Point AVIC_DEMO_SECRET at your own value to protect a
// shared demo database.
$demoSecret = getenv('AVIC_DEMO_SECRET') ?: 'Demo2026!';
$hash = password_hash($demoSecret, PASSWORD_DEFAULT);

/* Re-used demo literals, hoisted so the seed data reads consistently. */
const SEED_GARAGE_NAME    = 'Kigongo Motors';
const SEED_GARAGE_PHONE   = '+256 414 250 771';
const SEED_GARAGE_ADDRESS = 'Plot 14, Ntinda Industrial Area';
const MIME_IMAGE_JPEG     = 'image/jpeg';
const MIME_APP_PDF        = 'application/pdf';
const SEED_REJECTED_AT    = '2026-08-18 16:40';
const CLAIM_SUBMITTED     = 'Claim submitted';

function seedUsers(PDO $conn, string $hash): void
{
    $users = [
        1 => ['Ahumuza Doreen', 'doreen@example.ug', '+256 772 114 902', 'claimant', 'active', '2025-11-02'],
        2 => ['Okot Brian', 'brian.okot@avic.ug', '+256 701 553 118', 'adjuster', 'active', '2025-06-14'],
        3 => [SEED_GARAGE_NAME, 'desk@kigongomotors.ug', SEED_GARAGE_PHONE, 'garage', 'active', '2025-08-21'],
        5 => ['Mugisha Alex', 'alex.m@example.ug', '+256 758 209 663', 'claimant', 'active', '2026-02-17'],
        6 => ['Namara Pride', 'pride.n@example.ug', '+256 703 887 145', 'claimant', 'pending', '2026-09-12'],
        7 => ['Ssemwanga Autoworks', 'info@ssemwanga.ug', '+256 392 110 448', 'garage', 'active', '2026-09-10'],
        8 => ['Atuhaire Grace', 'grace.a@avic.ug', '+256 772 448 210', 'adjuster', 'active', '2025-09-30'],
        9 => ['Kato Ronald', 'r.kato@example.ug', '+256 706 331 992', 'claimant', 'suspended', '2025-12-04'],
    ];

    $stmt = $conn->prepare(
        'INSERT INTO users (id, uuid, full_name, email, phone, role, status, password_hash, email_verified_at, last_login)
         VALUES (:id, :uuid, :name, :email, :phone, :role, :status, :hash, NOW(), :last)
         ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = VALUES(email), phone = VALUES(phone),
             role = VALUES(role), status = VALUES(status), password_hash = VALUES(password_hash)'
    );
    foreach ($users as $id => [$name, $email, $phone, $role, $status, $created]) {
        $stmt->execute([
            'id' => $id,
            'uuid' => sprintf('%08x-0000-4000-8000-%012x', $id * 31, $id * 97),
            'name' => $name, 'email' => $email, 'phone' => $phone,
            'role' => $role, 'status' => $status, 'hash' => $hash,
            'last' => $status === 'active' ? $created . ' 09:00:00' : null,
        ]);
    }
    echo "  users          : " . count($users) . " demo accounts\n";
}

function seedPolicies(PDO $conn): void
{
    $rows = [
        [1, 1, 'POL-UG-88421', 'Toyota', 'Premio', 2016, 'JTD1234567890ABCD', 'UBG 442H', 'comprehensive', 42000000, 1850000, '2026-01-15', '2027-01-14', 'active'],
        [2, 1, 'POL-UG-90137', 'Nissan', 'X-Trail', 2013, 'JN8AS5MT0DW123456', 'UAX 019Z', 'third_party', 12000000, 640000, '2025-09-01', '2026-08-31', 'expired'],
        [3, 5, 'POL-UG-77310', 'Toyota', 'Hiace', 2011, 'JTFR1234567891XYZ', 'UBB 771K', 'comprehensive', 30000000, 1420000, '2026-03-01', '2027-02-28', 'active'],
        [4, 9, 'POL-UG-65002', 'Subaru', 'Forester', 2014, 'JF2SJ6DC0EH123987', 'UAP 650C', 'basic', 18000000, 890000, '2026-04-20', '2027-04-19', 'active'],
    ];
    $stmt = $conn->prepare(
        'INSERT INTO policies (id, user_id, policy_number, vehicle_make, vehicle_model, vehicle_year, vehicle_vin,
                               vehicle_plate, coverage_type, coverage_limit, premium, start_date, end_date, status)
         VALUES (:id, :uid, :num, :mk, :md, :yr, :vin, :plate, :cov, :lim, :prem, :start, :end, :status)
         ON DUPLICATE KEY UPDATE vehicle_make = VALUES(vehicle_make), vehicle_model = VALUES(vehicle_model),
             vehicle_vin = VALUES(vehicle_vin), vehicle_plate = VALUES(vehicle_plate), status = VALUES(status)'
    );
    foreach ($rows as $r) {
        $stmt->execute(['id' => $r[0], 'uid' => $r[1], 'num' => $r[2], 'mk' => $r[3], 'md' => $r[4], 'yr' => $r[5],
            'vin' => $r[6], 'plate' => $r[7], 'cov' => $r[8], 'lim' => $r[9], 'prem' => $r[10],
            'start' => $r[11], 'end' => $r[12], 'status' => $r[13]]);
    }
    echo "  policies       : " . count($rows) . "\n";
}

function seedClaims(PDO $conn): void
{
    $rows = [
        [101, 'CLM-2026-00042', 1, 1, 2, '2026-09-04', 'Jinja Road roundabout, Kampala',
         'Rear-ended while stationary at the traffic lights. Bumper, tail lights and boot lid damaged. The other driver stopped and police were called to the scene.',
         'collision', 6850000, null, 'CPS/KLA/2026/1184', 'under_review', '2026-09-05 09:22', null, null, '2026-09-04 18:40', null],
        [102, 'CLM-2026-00038', 1, 1, 2, '2026-07-19', 'Entebbe Road, Kajjansi',
         'Windscreen shattered by stone thrown up from a truck. No other vehicle involved.',
         'other', 1200000, 1100000, null, 'paid', '2026-07-19 15:10', '2026-07-22 11:02', '2026-07-28 10:15', '2026-07-19 14:50', null],
        [103, 'CLM-2026-00051', 1, 1, null, '2026-09-14', '',
         'Side mirror and left door scraped in the car park.',
         'vandalism', null, null, null, 'draft', null, null, null, '2026-09-14 20:05', null],
        [104, 'CLM-2026-00047', 5, 3, 2, '2026-08-28', 'Masaka–Mbarara highway, Lyantonde',
         'Head-on side swipe with a lorry overtaking on a bend. Passenger side panels and front axle affected.',
         'collision', 14300000, null, 'LYT/2026/0442', 'pending_docs', '2026-08-29 08:05', '2026-09-02 14:20', null, '2026-08-28 21:15', null],
        [105, 'CLM-2026-00049', 5, 3, 8, '2026-09-09', 'Nakawa market parking, Kampala',
         'Vehicle stolen from the market parking between 14:00 and 16:00. Reported at Nakawa Police Station the same evening.',
         'theft', 28000000, null, 'NKW/2026/3390', 'submitted', '2026-09-10 07:44', null, null, '2026-09-09 19:30', null],
        [106, 'CLM-2026-00044', 9, 4, 2, '2026-08-11', 'Gulu town, Pece division',
         'Bonnet and engine bay fire after a short circuit while parked overnight.',
         'fire', 9600000, 0, 'GLU/2026/0771', 'rejected', '2026-08-12 10:02', SEED_REJECTED_AT, SEED_REJECTED_AT, '2026-08-11 23:12',
         'Policy POL-UG-65002 is a basic cover tier; fire damage is not a covered peril under this tier.'],
        [107, 'CLM-2026-00040', 5, 3, 8, '2026-07-30', 'Bweyogerere, Wakiso',
         'Flood water damage to the cabin and electricals after heavy rain.',
         'natural_disaster', 5200000, 4800000, null, 'approved', '2026-07-31 09:00', '2026-08-05 13:15', null, '2026-07-30 22:41', null],
        [108, 'CLM-2026-00053', 1, 1, 2, '2026-08-22', 'Kireka stage, Wakiso',
         'Left wing mirror and door skin damaged by a boda boda squeezing past in traffic.',
         'collision', 2400000, 2150000, null, 'approved', '2026-08-23 08:15', '2026-08-30 11:40', null, '2026-08-22 18:05', null],
    ];
    $stmt = $conn->prepare(
        'INSERT INTO claims (id, claim_number, user_id, policy_id, adjuster_id, incident_date, incident_location,
                             incident_description, claim_type, estimated_damage, approved_amount, police_report_ref,
                             status, submitted_at, reviewed_at, resolved_at, created_at, rejection_reason)
         VALUES (:id, :cn, :uid, :pid, :aid, :idate, :loc, :desc, :ctype, :est, :appr, :pref,
                 :status, :sub, :rev, :res, :created, :reason)
         ON DUPLICATE KEY UPDATE incident_location = VALUES(incident_location), incident_description = VALUES(incident_description),
             status = VALUES(status), estimated_damage = VALUES(estimated_damage), approved_amount = VALUES(approved_amount),
             submitted_at = VALUES(submitted_at), reviewed_at = VALUES(reviewed_at), resolved_at = VALUES(resolved_at)'
    );
    foreach ($rows as $r) {
        $stmt->execute([
            'id' => $r[0], 'cn' => $r[1], 'uid' => $r[2], 'pid' => $r[3], 'aid' => $r[4], 'idate' => $r[5], 'loc' => $r[6],
            'desc' => $r[7], 'ctype' => $r[8], 'est' => $r[9], 'appr' => $r[10], 'pref' => $r[11], 'status' => $r[12],
            'sub' => $r[13], 'rev' => $r[14], 'res' => $r[15], 'created' => $r[16], 'reason' => $r[17],
        ]);
    }
    echo "  claims         : " . count($rows) . "\n";
}

function seedDocuments(PDO $conn): void
{
    $rows = [
        [1, 101, 1, 'accident_photo', 'rear-bumper.jpg', 2415000, MIME_IMAGE_JPEG, 1, 2, '2026-09-06 10:10', '2026-09-04 18:44'],
        [2, 101, 1, 'accident_photo', 'tail-light.jpg', 1880000, MIME_IMAGE_JPEG, 1, 2, '2026-09-06 10:11', '2026-09-04 18:45'],
        [3, 101, 1, 'police_report', 'police-report.pdf', 640000, MIME_APP_PDF, 0, null, null, '2026-09-05 08:50'],
        [4, 101, 3, 'repair_estimate', 'kigongo-quote.pdf', 310000, MIME_APP_PDF, 0, null, null, '2026-09-08 11:02'],
        [5, 104, 5, 'accident_photo', 'side-panel.jpg', 3120000, MIME_IMAGE_JPEG, 1, 2, '2026-09-02 14:05', '2026-08-28 21:20'],
        [6, 104, 5, 'police_report', 'lyantonde-pr.pdf', 720000, MIME_APP_PDF, 0, null, null, '2026-08-29 07:55'],
        [7, 102, 1, 'accident_photo', 'windscreen.jpg', 1450000, MIME_IMAGE_JPEG, 1, 2, '2026-07-22 10:40', '2026-07-19 15:02'],
        [8, 105, 5, 'police_report', 'nakawa-theft.pdf', 505000, MIME_APP_PDF, 0, null, null, '2026-09-10 07:40'],
        [9, 107, 5, 'vehicle_photo', 'cabin-water.jpg', 2210000, MIME_IMAGE_JPEG, 1, 8, '2026-08-05 12:50', '2026-07-30 22:50'],
        [10, 108, 1, 'accident_photo', 'wing-mirror.jpg', 980000, MIME_IMAGE_JPEG, 1, 2, '2026-08-30 11:20', '2026-08-22 18:10'],
    ];
    $stmt = $conn->prepare(
        'INSERT INTO claim_documents (id, claim_id, uploaded_by, doc_type, original_name, file_size, mime_type,
                                      is_verified, verified_by, verified_at, uploaded_at, stored_name, file_path)
         VALUES (:id, :cid, :by, :type, :name, :size, :mime, :ver, :vby, :vat, :uat, "", "")
         ON DUPLICATE KEY UPDATE is_verified = VALUES(is_verified), verified_by = VALUES(verified_by), verified_at = VALUES(verified_at)'
    );
    foreach ($rows as $r) {
        $stmt->execute(['id' => $r[0], 'cid' => $r[1], 'by' => $r[2], 'type' => $r[3], 'name' => $r[4],
            'size' => $r[5], 'mime' => $r[6], 'ver' => $r[7], 'vby' => $r[8], 'vat' => $r[9], 'uat' => $r[10]]);
    }
    echo "  documents      : " . count($rows) . " (metadata; uploads become real files)\n";
}

function seedEstimates(PDO $conn): void
{
    $rows = [
        [1, 101, 3, SEED_GARAGE_NAME, SEED_GARAGE_ADDRESS, SEED_GARAGE_PHONE, 4200000, 2100000, 550000, 6850000, 9, 'pending', null, '2026-09-08 11:00'],
        [2, 107, 3, SEED_GARAGE_NAME, SEED_GARAGE_ADDRESS, SEED_GARAGE_PHONE, 3100000, 1750000, 350000, 5200000, 6, 'approved', 'Electrical harness replacement reduced to a repair. Approved at UGX 4,800,000.', '2026-08-02 09:15'],
        [3, 104, 3, SEED_GARAGE_NAME, SEED_GARAGE_ADDRESS, SEED_GARAGE_PHONE, 9800000, 3900000, 600000, 14300000, 21, 'rejected', 'Front axle assembly quoted at new-part price; a reconditioned unit is acceptable. Please resubmit.', '2026-09-01 10:30'],
    ];
    $stmt = $conn->prepare(
        'INSERT INTO garage_estimates (id, claim_id, garage_user_id, garage_name, garage_address, garage_phone,
                                       parts_cost, labor_cost, other_cost, total_estimate, repair_days, status,
                                       adjuster_notes, created_at)
         VALUES (:id, :cid, :gid, :gname, :gaddr, :gphone, :p, :l, :o, :t, :d, :status, :notes, :created)
         ON DUPLICATE KEY UPDATE status = VALUES(status), adjuster_notes = VALUES(adjuster_notes)'
    );
    foreach ($rows as $r) {
        $stmt->execute(['id' => $r[0], 'cid' => $r[1], 'gid' => $r[2], 'gname' => $r[3], 'gaddr' => $r[4], 'gphone' => $r[5],
            'p' => $r[6], 'l' => $r[7], 'o' => $r[8], 't' => $r[9], 'd' => $r[10], 'status' => $r[11], 'notes' => $r[12], 'created' => $r[13]]);
    }
    echo "  estimates      : " . count($rows) . "\n";
}

function seedWorkOrders(PDO $conn): void
{
    $rows = [
        [1, 101, 3, 2, '2026-09-06 12:00', '2026-09-13', 'quoted'],
        [2, 104, 3, 2, '2026-08-30 09:10', '2026-09-06', 'revision_requested'],
        [3, 107, 3, 8, '2026-08-01 08:00', '2026-08-08', 'closed'],
    ];
    $stmt = $conn->prepare(
        'INSERT INTO work_orders (id, claim_id, garage_user_id, assigned_by, assigned_at, due, status)
         VALUES (:id, :cid, :gid, :by, :at, :due, :status)
         ON DUPLICATE KEY UPDATE status = VALUES(status), due = VALUES(due)'
    );
    foreach ($rows as $r) {
        $stmt->execute(['id' => $r[0], 'cid' => $r[1], 'gid' => $r[2], 'by' => $r[3], 'at' => $r[4], 'due' => $r[5], 'status' => $r[6]]);
    }
    echo "  work orders    : " . count($rows) . "\n";
}

function seedPayouts(PDO $conn): void
{
    $rows = [
        [1, 102, 1, 2, 1100000, 'mobile_money', 'Ahumuza Doreen', '+256 772 114 902', null, 'PAY-20260728-00113', 'completed', '2026-07-28 10:15', '2026-07-26 09:40'],
        [2, 107, 5, 2, 4800000, 'bank_transfer', 'Mugisha Alex', '01620****9', 'Stanbic Bank Uganda', 'PAY-20260906-00121', 'processing', null, '2026-09-06 15:20'],
    ];
    $stmt = $conn->prepare(
        'INSERT INTO payouts (id, claim_id, user_id, approved_by, amount, payment_method, account_name, account_number,
                              bank_name, reference_number, status, processed_at, created_at)
         VALUES (:id, :cid, :uid, :by, :amt, :method, :acc, :num, :bank, :ref, :status, :at, :created)
         ON DUPLICATE KEY UPDATE status = VALUES(status)'
    );
    foreach ($rows as $r) {
        $stmt->execute(['id' => $r[0], 'cid' => $r[1], 'uid' => $r[2], 'by' => $r[3], 'amt' => $r[4], 'method' => $r[5],
            'acc' => $r[6], 'num' => $r[7], 'bank' => $r[8], 'ref' => $r[9], 'status' => $r[10], 'at' => $r[11], 'created' => $r[12]]);
    }
    echo "  payouts        : " . count($rows) . "\n";
}

function seedNotifications(PDO $conn): void
{
    $rows = [
        [1, 1, 101, 'doc_verified', 'Two photos verified', 'Okot Brian verified the rear bumper and tail light photos on CLM-2026-00042.', 0, '2026-09-06 10:12'],
        [2, 1, 101, 'status_changed', 'Claim moved to under review', 'CLM-2026-00042 is now with an adjuster. Expect a decision by 19 Sep.', 0, '2026-09-05 09:30'],
        [3, 1, 102, 'payout', 'Payout completed', 'UGX 1,100,000 sent to your mobile money line. Reference PAY-20260728-00113.', 1, '2026-07-28 10:16'],
        [4, 2, 105, 'claim_assigned', 'New claim in your queue', 'CLM-2026-00049 (theft, UGX 28,000,000) was routed to you.', 0, '2026-09-10 07:45'],
        [5, 2, 101, 'estimate', 'Garage estimate submitted', 'Kigongo Motors quoted UGX 6,850,000 on CLM-2026-00042.', 0, '2026-09-08 11:05'],
        [6, 3, 104, 'estimate', 'Revision requested', 'Adjuster asked for a reconditioned-part quote on CLM-2026-00047.', 0, '2026-09-01 16:40'],
        [7, 3, 101, 'work_order', 'Work order assigned', 'You have been assigned CLM-2026-00042. Quote due 13 Sep.', 1, '2026-09-06 12:01'],
    ];
    $stmt = $conn->prepare(
        'INSERT INTO notifications (id, user_id, claim_id, type, title, message, is_read, created_at)
         VALUES (:id, :uid, :cid, :type, :title, :msg, :read, :at)
         ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message), is_read = VALUES(is_read)'
    );
    foreach ($rows as $r) {
        $stmt->execute(['id' => $r[0], 'uid' => $r[1], 'cid' => $r[2], 'type' => $r[3], 'title' => $r[4], 'msg' => $r[5], 'read' => $r[6], 'at' => $r[7]]);
    }
    echo "  notifications  : " . count($rows) . "\n";
}

function seedSettings(PDO $conn): void
{
    $rows = [
        'company_name' => 'AVIC Insurance Co.',
        'payout_currency' => 'UGX',
        'max_upload_mb' => '10',
        'claims_sla_days' => '14',
        'auto_assign' => 'round_robin',
        'support_email' => 'support@avic.ug',
    ];
    $stmt = $conn->prepare('INSERT INTO settings (key_name, value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach ($rows as $k => $v) {
        $stmt->execute(['k' => $k, 'v' => $v]);
    }
    echo "  settings       : " . count($rows) . "\n";
}

function seedHistory(PDO $conn): void
{
    $rows = [
        [101, 1, 'draft', 'submitted', CLAIM_SUBMITTED, '2026-09-05 09:22'],
        [101, 2, 'submitted', 'under_review', 'Review started', '2026-09-05 11:00'],
        [102, 1, 'draft', 'submitted', CLAIM_SUBMITTED, '2026-07-19 15:10'],
        [102, 2, 'submitted', 'approved', 'Approved at 1,100,000 UGX', '2026-07-22 11:02'],
        [104, 1, 'draft', 'submitted', CLAIM_SUBMITTED, '2026-08-29 08:05'],
        [104, 2, 'submitted', 'pending_docs', 'More documents requested', '2026-09-02 14:20'],
        [105, 5, 'draft', 'submitted', CLAIM_SUBMITTED, '2026-09-10 07:44'],
        [106, 9, 'draft', 'submitted', CLAIM_SUBMITTED, '2026-08-12 10:02'],
        [106, 2, 'submitted', 'rejected', 'Not a covered peril', '2026-08-18 16:40'],
        [107, 5, 'draft', 'submitted', CLAIM_SUBMITTED, '2026-07-31 09:00'],
        [107, 8, 'submitted', 'approved', 'Approved at 4,800,000 UGX', '2026-08-05 13:15'],
        [108, 1, 'draft', 'submitted', CLAIM_SUBMITTED, '2026-08-23 08:15'],
        [108, 2, 'submitted', 'approved', 'Approved at 2,150,000 UGX', '2026-08-30 11:40'],
    ];
    /* dedupe-ish by natural key is awkward here; wipe history for seeded claims first */
    $conn->exec('DELETE FROM claim_status_history WHERE claim_id BETWEEN 101 AND 108');
    $stmt = $conn->prepare(
        'INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes, changed_at)
         VALUES (:cid, :by, :from, :to, :notes, :at)'
    );
    foreach ($rows as $r) {
        $stmt->execute(['cid' => $r[0], 'by' => $r[1], 'from' => $r[2], 'to' => $r[3], 'notes' => $r[4], 'at' => $r[5]]);
    }
    echo "  status history : " . count($rows) . "\n";
}

if (PHP_SAPI === 'cli') {
    echo "Seeding demo data into {$conn->query('SELECT DATABASE()')->fetchColumn()}...\n";
    seedUsers($conn, $hash);
    seedPolicies($conn);
    seedClaims($conn);
    seedDocuments($conn);
    seedEstimates($conn);
    seedWorkOrders($conn);
    seedPayouts($conn);
    seedNotifications($conn);
    seedSettings($conn);
    seedHistory($conn);
    echo 'Done. Any demo account signs in with password: ' . $demoSecret . "\n";
}
