<?php
declare(strict_types=1);

use Avic\Core\Db;
use Avic\Core\HttpException;
use Avic\Core\RuleViolation;
use Avic\Core\ValidationException;
use Avic\Services\ActivityService;
use Avic\Services\ClaimService;
use Avic\Services\FinancialService;

T::test('fnol: creates an open, numbered, assigned claim with exposures and activities', function () {
    $adj = Fx::adjuster(['region' => 'central']);
    [$id, , $claimant] = Fx::claim([], $adj);
    $c = Db::one('SELECT * FROM claims WHERE id = ?', [$id]);
    T::same('open', $c['status']);
    T::same(sprintf('CLM-%s-%06d', date('Y'), $id), $c['claim_number']);
    T::same($adj['id'], (int)$c['assigned_to']);
    T::same(null, $c['flags']);
    T::same(1, (int)Db::val('SELECT COUNT(*) FROM exposures WHERE claim_id = ?', [$id]));
    $subjects = array_column(Db::all('SELECT template_key FROM activities WHERE claim_id = ?', [$id]), 'template_key');
    foreach (['contact_claimant', 'verify_coverage', 'inspect_vehicle', 'repair_estimate'] as $k) {
        T::true(in_array($k, $subjects, true), "activity $k");
    }
    T::same($adj['id'], (int)Db::val("SELECT assigned_to FROM activities WHERE template_key = 'verify_coverage' AND claim_id = ?", [$id]));
    T::true((int)Db::val("SELECT COUNT(*) FROM audit_log WHERE claim_id = ? AND action = 'claim.created'", [$id]) === 1);
});

T::test('fnol: validation collects field errors and writes nothing', function () {
    $claimant = Fx::user();
    $policy = Fx::policy($claimant['id']);
    try {
        ClaimService::fnol($policy, Fx::fnolInput(['loss_date' => date('Y-m-d', strtotime('+2 days')), 'loss_type' => 'x', 'description' => 'short', 'exposures' => []]), $claimant);
        throw new RuntimeException('should have failed');
    } catch (ValidationException $e) {
        foreach (['loss_date', 'loss_type', 'description', 'exposures'] as $f) {
            T::true(isset($e->errors()[$f]), "error for $f");
        }
    }
    T::same(0, (int)Db::val('SELECT COUNT(*) FROM claims'));
});

T::test('fnol: third-party policy rejects an own-vehicle exposure', function () {
    $claimant = Fx::user();
    $policy = Fx::policy($claimant['id'], ['coverage_type' => 'third_party']);
    T::throws(ValidationException::class, fn() => ClaimService::fnol($policy, Fx::fnolInput(), $claimant), 'third-party policy');
});

T::test('fnol: a loss outside the policy period is accepted but flagged and blocks payment', function () {
    $adj = Fx::adjuster(['region' => 'central']);
    $claimant = Fx::user();
    $policy = Fx::policy($claimant['id'], ['start_date' => date('Y-m-d', strtotime('-20 days'))]);
    $id = ClaimService::fnol($policy, Fx::fnolInput(['loss_date' => date('Y-m-d', strtotime('-40 days'))]), $claimant);
    $claim = ClaimService::get($id, $adj);
    T::same(1, count(ClaimService::flags($claim)));
    T::true(Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'review_flags'", [$id]) !== null);

    Fx::investigate($id, $adj);
    $x = Fx::exposureId($id);
    FinancialService::setReserve($id, $x, 500000, '', $adj);
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $x, 100000, 'Garage', 'cheque', '', $adj), 'flagged');
    T::throws(RuleViolation::class, fn() => ClaimService::transition($id, 'settled', $adj), 'flagged');

    ActivityService::complete((int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'review_flags'", [$id]), $adj);
    T::same(null, Db::val('SELECT flags FROM claims WHERE id = ?', [$id]));
    FinancialService::issuePayment($id, $x, 100000, 'Garage', 'cheque', '', $adj);
});

T::test('fnol: a second claim for the same policy, date and type is flagged as a possible duplicate', function () {
    $claimant = Fx::user();
    $policy = Fx::policy($claimant['id']);
    $first = ClaimService::fnol($policy, Fx::fnolInput(), $claimant);
    $second = ClaimService::fnol($policy, Fx::fnolInput(), $claimant);
    $flags = json_decode((string)Db::val('SELECT flags FROM claims WHERE id = ?', [$second]), true);
    T::same(1, count($flags));
    T::contains((string)Db::val('SELECT claim_number FROM claims WHERE id = ?', [$first]), $flags[0]);
});

T::test('visibility: a claimant only sees claims on their own policies', function () {
    [$id, $adj, $owner] = Fx::claim();
    T::same($id, (int)ClaimService::get($id, $owner)['id']);
    T::same($id, (int)ClaimService::get($id, $adj)['id']);
    $stranger = Fx::user();
    T::throws(HttpException::class, fn() => ClaimService::get($id, $stranger));
    T::same(0, ClaimService::search([], $stranger)['total']);
    T::same(1, ClaimService::search([], $owner)['total']);
});

T::test('activity: completing "Verify policy coverage" starts the investigation', function () {
    [$id, $adj] = Fx::claim();
    T::same('open', Fx::status($id));
    Fx::investigate($id, $adj);
    T::same('investigating', Fx::status($id));
    T::true((int)Db::val("SELECT COUNT(*) FROM activities WHERE claim_id = ? AND template_key = 'investigation_report'", [$id]) === 1);
    T::throws(RuleViolation::class, fn() => ActivityService::complete(
        (int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'verify_coverage'", [$id]), $adj), 'already');
});

T::test('activity: mandatory activities cannot be skipped, optional ones can', function () {
    [$id, $adj] = Fx::claim();
    $mand = (int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'verify_coverage'", [$id]);
    $opt = (int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'contact_claimant'", [$id]);
    T::throws(RuleViolation::class, fn() => ActivityService::skip($mand, $adj), 'Mandatory');
    ActivityService::skip($opt, $adj);
    T::same('skipped', Db::val('SELECT status FROM activities WHERE id = ?', [$opt]));
});

T::test('activity: manual activity is validated and assigned to the claim handler', function () {
    [$id, $adj] = Fx::claim();
    T::throws(Avic\Core\ValidationException::class, fn() => ActivityService::create($id, ['subject' => ''], $adj));
    $a = ActivityService::create($id, ['subject' => 'Call the garage', 'priority' => 'high', 'due_date' => date('Y-m-d', strtotime('+2 days'))], $adj);
    T::same($adj['id'], (int)Db::val('SELECT assigned_to FROM activities WHERE id = ?', [$a]));
});

T::test('lifecycle guards: cannot settle without a reserve; skipping states is refused', function () {
    [$id, $adj] = Fx::claim();
    T::throws(RuleViolation::class, fn() => ClaimService::transition($id, 'settled', $adj), 'cannot move');
    Fx::investigate($id, $adj);
    T::throws(RuleViolation::class, fn() => ClaimService::transition($id, 'settled', $adj), 'reserve');
    FinancialService::setReserve($id, Fx::exposureId($id), 1000000, '', $adj);
    ClaimService::transition($id, 'settled', $adj);
    T::same('settled', Fx::status($id));
});

T::test('closing: outcome rules, reserve release, skipped activities, exposures closed', function () {
    [$id, $adj] = Fx::claim();
    Fx::investigate($id, $adj);
    $x = Fx::exposureId($id);
    FinancialService::setReserve($id, $x, 800000, '', $adj);
    T::throws(RuleViolation::class, fn() => ClaimService::transition($id, 'closed', $adj), 'Choose how');
    T::throws(RuleViolation::class, fn() => ClaimService::transition($id, 'closed', $adj, 'paid'), 'Choose how');
    ClaimService::transition($id, 'closed', $adj, 'denied');
    $c = Db::one('SELECT * FROM claims WHERE id = ?', [$id]);
    T::same('closed', $c['status']);
    T::same('denied', $c['close_outcome']);
    T::same(0, FinancialService::claimTotals($id)['available'], 'reserve released');
    T::same(0, (int)Db::val("SELECT COUNT(*) FROM activities WHERE claim_id = ? AND status = 'open'", [$id]));
    T::same(0, (int)Db::val("SELECT COUNT(*) FROM exposures WHERE claim_id = ? AND status = 'open'", [$id]));
    T::throws(RuleViolation::class, fn() => ClaimService::assign($id, null, $adj), 'closed');
});

T::test('closing: a claim with payments can only close as paid, and needs a payment to do so', function () {
    [$id, $adj] = Fx::claim();
    Fx::investigate($id, $adj);
    $x = Fx::exposureId($id);
    FinancialService::setReserve($id, $x, 900000, '', $adj);
    FinancialService::issuePayment($id, $x, 400000, 'Kigongo Motors', 'bank_transfer', '', $adj);
    T::throws(RuleViolation::class, fn() => ClaimService::transition($id, 'closed', $adj, 'denied'), 'Payments have been issued');
    ClaimService::transition($id, 'settled', $adj);
    T::throws(RuleViolation::class, fn() => ClaimService::transition($id, 'closed', $adj, 'denied'), 'Choose how');
    ClaimService::transition($id, 'closed', $adj, 'paid');
    T::same(['reserve' => 400000, 'paid' => 400000, 'recovered' => 0, 'available' => 0], FinancialService::claimTotals($id));
});

T::test('reopen: returns to open, reopens exposures, creates a review activity', function () {
    [$id, $adj] = Fx::claim();
    ClaimService::transition($id, 'closed', $adj, 'withdrawn');
    ClaimService::transition($id, 'open', $adj);
    T::same('open', Fx::status($id));
    T::same(null, Db::val('SELECT close_outcome FROM claims WHERE id = ?', [$id]));
    T::same(1, (int)Db::val("SELECT COUNT(*) FROM exposures WHERE claim_id = ? AND status = 'open'", [$id]));
    $a = (int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'review_reopened' AND status = 'open'", [$id]);
    ActivityService::complete($a, $adj);
    T::same('investigating', Fx::status($id));
});

T::test('assign: reassigning moves the open activities; bad assignees are refused', function () {
    [$id, $adj] = Fx::claim();
    $other = Fx::adjuster();
    ClaimService::assign($id, $other['id'], $adj);
    T::same(0, (int)Db::val("SELECT COUNT(*) FROM activities WHERE claim_id = ? AND status = 'open' AND assigned_to <> ?", [$id, $other['id']]));
    T::throws(RuleViolation::class, fn() => ClaimService::assign($id, Fx::user()['id'], $adj), 'active adjuster');
    ClaimService::assign($id, null, $adj);
    T::same(null, Db::val('SELECT assigned_to FROM claims WHERE id = ?', [$id]));
});

T::test('exposures: added later, checked against the policy, and trigger activities', function () {
    [$id, $adj] = Fx::claim();
    ClaimService::addExposure($id, ['type' => 'third_party_injury', 'party' => 'John Okello', 'description' => 'Whiplash'], $adj);
    T::true(Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'medical_report'", [$id]) !== null);
    T::throws(RuleViolation::class, fn() => ClaimService::addExposure($id, ['type' => 'nonsense', 'party' => 'x'], $adj));
});

T::test('authorization: claimants cannot run adjuster operations', function () {
    [$id, , $claimant] = Fx::claim();
    T::throws(HttpException::class, fn() => ClaimService::transition($id, 'investigating', $claimant));
    T::throws(HttpException::class, fn() => ClaimService::assign($id, $claimant['id'], $claimant));
    T::throws(HttpException::class, fn() => FinancialService::setReserve($id, Fx::exposureId($id), 1000, '', $claimant));
    T::throws(HttpException::class, fn() => ActivityService::complete(1, $claimant));
});
