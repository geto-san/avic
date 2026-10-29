<?php
declare(strict_types=1);

require_once __DIR__ . '/HttpClient.php';

use Avic\Core\Db;

function flash_text(array $res): string
{
    return html_entity_decode(strip_tags($res['body']), ENT_QUOTES);
}

T::test('http: signed-out visitors are sent to the login page; headers are hardened', function () {
    $b = new Browser();
    $r = $b->get('/dashboard');
    T::same(303, $r['status']);
    T::same('/login', $r['location']);
    $login = $b->get('/login');
    T::same(200, $login['status']);
    T::same('nosniff', $login['headers']['x-content-type-options']);
    T::same('DENY', $login['headers']['x-frame-options']);
    T::contains("script-src 'self'", $login['headers']['content-security-policy']);
    T::same(200, $b->get('/assets/css/app.css')['status']);
    T::same(404, $b->get('/nope')['status']);
});

T::test('http: login — generic error for bad credentials, session for good ones, lockout after 5 failures', function () {
    $u = Fx::user();
    $b = new Browser();
    $bad = $b->login($u['email'], 'wrong');
    T::same(401, $bad['status']);
    T::contains('Incorrect email or password', $bad['body']);
    $none = $b->login('nobody@test.ug', 'wrong');
    T::contains('Incorrect email or password', $none['body'], 'same message for unknown emails');
    $ok = $b->login($u['email']);
    T::same(303, $ok['status']);
    T::same(200, $b->get('/dashboard')['status']);
    $b2 = new Browser();
    for ($i = 0; $i < 5; $i++) {
        $b2->login($u['email'], 'bad' . $i);
    }
    T::contains('Too many failed attempts', $b2->login($u['email'])['body'], 'locked even with the right password');
});

T::test('http: every POST needs a CSRF token', function () {
    $u = Fx::user();
    $b = new Browser();
    T::same(419, $b->post('/login', ['email' => $u['email'], 'password' => 'Passw0rd!'], false)['status']);
    $b->login($u['email']);
    T::same(419, $b->post('/logout', [], false)['status']);
    T::same(200, $b->get('/dashboard')['status'], 'still signed in');
});

T::test('http: registration makes a claimant and ignores a requested role', function () {
    $b = new Browser();
    $r = $b->post('/register', ['full_name' => 'Ann Test', 'email' => 'ann@test.ug', 'phone' => '0772123456', 'password' => 'longenough1', 'password2' => 'longenough1', 'role' => 'adjuster']);
    T::same(303, $r['status']);
    T::same('claimant', Db::val("SELECT role FROM users WHERE email = 'ann@test.ug'"));
    $bad = $b->post('/register', ['full_name' => '', 'email' => 'nope', 'password' => 'x', 'password2' => 'y']);
    T::same(422, $bad['status']);
    T::contains('Enter a valid email', $bad['body']);
});

T::test('http: a claimant links a policy with number + plate, and cannot link someone else\'s', function () {
    $mine = Fx::user();
    $other = Fx::user();
    $free = Fx::policy(null, ['policy_number' => 'POL-FREE', 'vehicle_plate' => 'UZZ 1A']);
    $taken = Fx::policy($other['id'], ['policy_number' => 'POL-TAKEN', 'vehicle_plate' => 'UZZ 2B']);
    $b = new Browser();
    $b->login($mine['email']);
    $r = $b->follow($b->post('/policies/link', ['policy_number' => 'POL-FREE', 'plate' => 'uzz1a']));
    T::contains('Policy linked', flash_text($r));
    T::same($mine['id'], (int)Db::val("SELECT user_id FROM policies WHERE policy_number = 'POL-FREE'"));
    T::contains('No policy that can be linked', flash_text($b->follow($b->post('/policies/link', ['policy_number' => 'POL-TAKEN', 'plate' => 'UZZ 2B']))));
    T::contains('No policy that can be linked', flash_text($b->follow($b->post('/policies/link', ['policy_number' => 'POL-FREE', 'plate' => 'WRONG']))), 'wrong plate for a policy that is already linked to me still must match');
});

T::test('http: claimant files a claim through the 3-step wizard; the adjuster handles it; a stranger gets 404', function () {
    $adj = Fx::adjuster(['region' => 'central']);
    $claimant = Fx::user();
    $policy = Fx::policy($claimant['id'], ['policy_number' => 'POL-WIZ']);
    $b = new Browser();
    $b->login($claimant['email']);

    $miss = $b->post('/fnol/search', ['policy_number' => 'POL-DOES-NOT-EXIST']);
    T::same(404, $miss['status']);
    $step1 = $b->post('/fnol/search', ['policy_number' => 'pol-wiz']);
    T::same('/fnol/details', $step1['location']);

    $bad = $b->post('/fnol/details', ['loss_date' => '', 'description' => 'x']);
    T::same(422, $bad['status']);
    T::contains('Enter the date of loss', $bad['body']);

    $details = [
        'loss_date' => date('Y-m-d', strtotime('-2 days')), 'loss_type' => 'collision', 'loss_region' => 'central',
        'loss_location' => 'Kampala Road', 'description' => 'Hit by a car <script>alert(1)</script> at the lights.', 'police_ref' => '',
        'exposures' => [['type' => 'own_vehicle', 'party' => 'Me', 'description' => 'Door'], ['type' => '', 'party' => '', 'description' => '']],
    ];
    T::same('/fnol/review', $b->post('/fnol/details', $details)['location']);
    $review = $b->get('/fnol/review');
    T::same(200, $review['status']);
    T::contains('Kampala Road', $review['body']);
    $done = $b->post('/fnol/submit');
    T::same(303, $done['status']);
    $page = $b->follow($done);
    T::same(200, $page['status']);
    T::contains('CLM-', $page['body']);
    T::same(false, str_contains($page['body'], '<script>alert(1)</script>'), 'description is escaped');
    T::contains('&lt;script&gt;', $page['body']);

    $claimId = (int)Db::val('SELECT id FROM claims');
    T::same(1, (int)Db::val('SELECT COUNT(*) FROM exposures'), 'the empty second row was ignored');
    T::same($adj['id'], (int)Db::val('SELECT assigned_to FROM claims'));

    $stranger = new Browser();
    $stranger->login(Fx::user()['email']);
    T::same(404, $stranger->get("/claims/$claimId")['status']);
    T::same(404, $stranger->post('/fnol/search', ['policy_number' => 'POL-WIZ'])['status'], "cannot start a claim on someone else's policy");

    $ab = new Browser();
    $ab->login($adj['email']);
    $view = $ab->get("/claims/$claimId?tab=activities");
    T::same(200, $view['status']);
    T::contains('Verify policy coverage', $view['body']);
});

T::test('http: claimants are blocked from adjuster actions and pages', function () {
    [$id, , $claimant] = Fx::claim();
    $b = new Browser();
    $b->login($claimant['email']);
    T::same(403, $b->post("/claims/$id/transition", ['to' => 'investigating'])['status']);
    T::same(403, $b->post("/claims/$id/reserve", ['exposure_id' => Fx::exposureId($id), 'amount' => '1000'])['status']);
    T::same(403, $b->post("/claims/$id/payments", ['exposure_id' => Fx::exposureId($id), 'amount' => '1000', 'payee' => 'me', 'method' => 'cheque'])['status']);
    T::same(403, $b->get('/activities')['status']);
    $tab = $b->get("/claims/$id?tab=financials");
    T::same(false, str_contains($tab['body'], 'Ledger'), 'the financials tab falls back to the summary for claimants');
    T::same('open', Fx::status($id));
});

T::test('http: adjuster runs the money flow; the overpayment is refused with a clear message', function () {
    [$id, $adj] = Fx::claim();
    Fx::investigate($id, $adj);
    $x = Fx::exposureId($id);
    $b = new Browser();
    $b->login($adj['email']);
    T::contains('Reserve updated', flash_text($b->follow($b->post("/claims/$id/reserve", ['exposure_id' => $x, 'amount' => '1,500,000', 'memo' => 'estimate']))));
    T::contains('Payment issued', flash_text($b->follow($b->post("/claims/$id/payments", ['exposure_id' => $x, 'amount' => '1000000', 'payee' => 'Garage', 'method' => 'cheque']))));
    $over = $b->follow($b->post("/claims/$id/payments", ['exposure_id' => $x, 'amount' => '600000', 'payee' => 'Garage', 'method' => 'cheque']));
    T::contains('Insufficient reserve', flash_text($over));
    T::contains('UGX 500,000', flash_text($over), 'shows what is left');
    $tab = $b->get("/claims/$id?tab=financials");
    T::contains('Ledger', $tab['body']);
    T::same(1, (int)Db::val("SELECT COUNT(*) FROM transactions WHERE type = 'payment'"));
    T::contains('Claim updated', flash_text($b->follow($b->post("/claims/$id/transition", ['to' => 'settled']))));
    T::contains('Claim updated', flash_text($b->follow($b->post("/claims/$id/transition", ['to' => 'closed', 'outcome' => 'paid']))));
    T::same('closed', Fx::status($id));
});

T::test('http: documents — images and PDFs are accepted, disguised files are not, access follows the claim', function () {
    [$id, $adj, $claimant] = Fx::claim();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $good = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($good, $png);
    $fake = tempnam(sys_get_temp_dir(), 'txt');
    file_put_contents($fake, '<?php echo "pwned"; ?>');

    $b = new Browser();
    $b->login($claimant['email']);
    $ok = $b->follow($b->upload("/claims/$id/documents", ['doc_type' => 'accident_photo'], $good, 'photo.png', 'image/png'));
    T::contains('Document uploaded', flash_text($ok));
    $bad = $b->follow($b->upload("/claims/$id/documents", ['doc_type' => 'other'], $fake, 'evil.png', 'image/png'));
    T::contains('Only JPEG, PNG and PDF', flash_text($bad));
    T::same(1, (int)Db::val('SELECT COUNT(*) FROM documents'));

    $docId = (int)Db::val('SELECT id FROM documents');
    $dl = $b->get("/documents/$docId");
    T::same(200, $dl['status']);
    T::same('image/png', $dl['headers']['content-type']);
    T::same($png, $dl['body']);
    T::contains('nosniff', $dl['headers']['x-content-type-options']);
    T::same([], glob(AVIC_ROOT . '/public/uploads') ?: [], 'nothing is stored under public/');
    T::same(1, count(glob(TestServer::$uploads . '/*')));

    $stranger = new Browser();
    $stranger->login(Fx::user()['email']);
    T::same(404, $stranger->get("/documents/$docId")['status']);
    T::same(404, $stranger->upload("/claims/$id/documents", ['doc_type' => 'other'], $good, 'x.png', 'image/png')['status']);
    $ab = new Browser();
    $ab->login($adj['email']);
    T::same(200, $ab->get("/documents/$docId")['status']);
    unlink($good);
    unlink($fake);
});

T::test('http: the claim list is scoped — adjusters filter by view, claimants only see their own', function () {
    [$id, $adj, $claimant] = Fx::claim();
    $other = Fx::adjuster(['region' => 'western']);
    $b = new Browser();
    $b->login($other['email']);
    T::same(false, str_contains($b->get('/claims?view=mine')['body'], 'CLM-'), 'not mine');
    T::contains('CLM-', $b->get('/claims?view=all')['body']);
    T::contains('Take this claim', $b->get("/claims/$id")['body']);
    T::contains('Assignment updated', flash_text($b->follow($b->post("/claims/$id/assign", ['assignee' => 'me']))));
    T::contains('CLM-', $b->get('/claims?view=mine')['body']);
    $c = new Browser();
    $c->login($claimant['email']);
    T::contains('CLM-', $c->get('/claims')['body']);
    T::same(false, str_contains($c->get('/claims')['body'], 'Handler'), 'claimants do not see internal columns');
});
