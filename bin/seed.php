<?php
declare(strict_types=1);

// Demo data for local development: staff, a policyholder, policies and a few claims in
// different stages — all created through the real services, so rules, activities and the
// ledger look exactly as they would in use. Safe to re-run (skips when already seeded).
require dirname(__DIR__) . '/src/bootstrap.php';

use Avic\Core\Db;
use Avic\Core\Env;
use Avic\Services\ActivityService;
use Avic\Services\ClaimService;
use Avic\Services\FinancialService;

if (Env::isProduction()) {
    fwrite(STDERR, "Refusing to seed demo data when AVIC_ENV=production.\n");
    exit(1);
}
if (Db::val('SELECT COUNT(*) FROM users') > 0) {
    echo "Database already has users; nothing seeded.\n";
    exit(0);
}
$secret = Env::get('AVIC_DEMO_SECRET', 'Demo2026!');
$hash = password_hash($secret, PASSWORD_DEFAULT);

$user = fn(string $name, string $email, string $role, ?string $region = null, ?string $spec = null): array =>
    ['id' => Db::insert('INSERT INTO users (full_name, email, password_hash, role, region, specialty) VALUES (?,?,?,?,?,?)', [$name, $email, $hash, $role, $region, $spec]),
     'role' => $role, 'full_name' => $name];

$brian = $user('Brian Okot', 'brian.okot@avic.ug', 'adjuster', 'central', 'collision');
$sarah = $user('Sarah Kyomuhendo', 'sarah.kyomuhendo@avic.ug', 'adjuster', 'western', 'theft');
$user('David Mugisha', 'david.mugisha@avic.ug', 'adjuster', 'central');
$user('Peter Opio', 'peter.opio@avic.ug', 'adjuster', 'northern');
$grace = $user('Grace Nakato', 'grace.nakato@example.ug', 'claimant');
$user('Moses Ssebunya', 'moses.ssebunya@example.ug', 'claimant');

$policy = function (string $no, ?int $uid, string $name, string $make, string $model, int $year, string $plate, string $cover, int $limit, string $from, string $to, string $status = 'active'): array {
    $id = Db::insert(
        'INSERT INTO policies (policy_number, user_id, insured_name, vehicle_make, vehicle_model, vehicle_year, vehicle_plate, coverage_type, coverage_limit, start_date, end_date, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$no, $uid, $name, $make, $model, $year, $plate, $cover, $limit, $from, $to, $status]
    );
    return Db::one('SELECT * FROM policies WHERE id = ?', [$id]);
};
$y = (int)date('Y');
$p1 = $policy('POL-DEMO-0001', $grace['id'], 'Grace Nakato', 'Toyota', 'Corolla', 2017, 'UBA 123X', 'comprehensive', 25000000, "$y-01-01", "$y-12-31");
$p2 = $policy('POL-DEMO-0002', $grace['id'], 'Grace Nakato', 'Honda', 'Fit', 2015, 'UBB 456Y', 'third_party', 10000000, "$y-01-01", "$y-12-31");
$policy('POL-DEMO-0003', null, 'Moses Ssebunya', 'Subaru', 'Forester', 2019, 'UCC 789Z', 'comprehensive', 40000000, "$y-01-01", "$y-12-31");
$policy('POL-DEMO-0004', null, 'Esther Auma', 'Toyota', 'Hilux', 2020, 'UDD 321A', 'comprehensive', 60000000, ($y - 1) . '-01-01', ($y - 1) . '-12-31');

$day = fn(int $ago) => date('Y-m-d', strtotime("-$ago days"));
$in = fn(array $o) => $o + ['police_ref' => '', 'exposures' => []];

// 1. A fresh collision in the central region: open, auto-assigned to the collision specialist.
ClaimService::fnol($p1, $in([
    'loss_date' => $day(2), 'loss_type' => 'collision', 'loss_region' => 'central', 'loss_location' => 'Entebbe Road near Zana',
    'description' => 'Hit from behind by a taxi while stopped at the junction.',
    'exposures' => [['type' => 'own_vehicle', 'party' => 'Grace Nakato', 'description' => 'Rear bumper and boot lid']],
]), $grace);

// 2. A theft in the west: assigned to the theft specialist, investigating, with money moving.
$id = ClaimService::fnol($p1, $in([
    'loss_date' => $day(12), 'loss_type' => 'theft', 'loss_region' => 'western', 'loss_location' => 'Mbarara town, High Street',
    'description' => 'Side mirrors and the car radio were stolen overnight.', 'police_ref' => 'CRB 118/2026',
    'exposures' => [['type' => 'own_vehicle', 'party' => 'Grace Nakato', 'description' => 'Two mirrors and radio']],
]), $grace);
$verify = (int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'verify_coverage'", [$id]);
ActivityService::complete($verify, $sarah);
$x = (int)Db::val('SELECT id FROM exposures WHERE claim_id = ?', [$id]);
FinancialService::setReserve($id, $x, 1800000, 'Parts and fitting estimate', $sarah);
FinancialService::issuePayment($id, $x, 900000, 'Kigongo Motors', 'bank_transfer', '', $sarah);

// 3. A third-party injury claim that is settled and paid.
$id = ClaimService::fnol($p2, $in([
    'loss_date' => $day(40), 'loss_type' => 'collision', 'loss_region' => 'central', 'loss_location' => 'Jinja Road, Nakawa',
    'description' => 'Reversed into a boda boda rider at slow speed.',
    'exposures' => [['type' => 'third_party_injury', 'party' => 'John Okello', 'description' => 'Leg injury']],
]), $grace);
ActivityService::complete((int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'verify_coverage'", [$id]), $brian);
$x = (int)Db::val('SELECT id FROM exposures WHERE claim_id = ?', [$id]);
FinancialService::setReserve($id, $x, 3000000, 'Medical costs', $brian);
FinancialService::issuePayment($id, $x, 3000000, 'Mulago Hospital', 'cheque', 'CHQ-0042', $brian);
ClaimService::transition($id, 'settled', $brian);
ClaimService::transition($id, 'closed', $brian, 'paid');

echo "Seeded. Password for every demo account: $secret\n";
echo "  claimant  grace.nakato@example.ug   (policies POL-DEMO-0001, POL-DEMO-0002)\n";
echo "  claimant  moses.ssebunya@example.ug (link POL-DEMO-0003 with plate UCC 789Z)\n";
echo "  adjuster  brian.okot@avic.ug        (also sarah.kyomuhendo@, david.mugisha@, peter.opio@avic.ug)\n";
