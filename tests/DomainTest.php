<?php
declare(strict_types=1);

use Avic\Domain\Assignment;
use Avic\Domain\ClaimLifecycle;
use Avic\Domain\CoverageCheck;
use Avic\Domain\Rules;

T::test('lifecycle: only the documented moves are allowed', function () {
    T::true(ClaimLifecycle::canMove('open', 'investigating'));
    T::true(ClaimLifecycle::canMove('settled', 'closed'));
    T::true(ClaimLifecycle::canMove('closed', 'open'));
    T::same(false, ClaimLifecycle::canMove('open', 'settled'), 'cannot skip investigation');
    T::same(false, ClaimLifecycle::canMove('settled', 'investigating'));
    T::same(false, ClaimLifecycle::canMove('closed', 'settled'));
    T::same(['paid'], ClaimLifecycle::outcomesFor('settled'));
    T::same(['denied', 'withdrawn'], ClaimLifecycle::outcomesFor('investigating'));
});

T::test('coverage: flags a loss outside the policy period and a cancelled policy', function () {
    $p = ['status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'coverage_type' => 'comprehensive'];
    T::same([], CoverageCheck::flags($p, '2026-06-01'));
    T::same(1, count(CoverageCheck::flags($p, '2025-12-31')));
    T::same(2, count(CoverageCheck::flags(['status' => 'cancelled'] + $p, '2027-01-01')));
});

T::test('coverage: third-party policies do not cover the insured vehicle', function () {
    T::true(CoverageCheck::exposureProblem(['coverage_type' => 'third_party'], 'own_vehicle') !== null);
    T::same(null, CoverageCheck::exposureProblem(['coverage_type' => 'third_party'], 'third_party_injury'));
    T::same(null, CoverageCheck::exposureProblem(['coverage_type' => 'comprehensive'], 'own_vehicle'));
});

T::test('rules: activity templates follow the event and filters', function () {
    $keys = fn(array $l) => array_column($l, 'key');
    $opened = $keys(Rules::activitiesFor('claim.opened', ['loss_type' => 'theft', 'flagged' => false]));
    T::true(in_array('verify_coverage', $opened, true));
    T::true(in_array('obtain_police_report', $opened, true));
    T::same(false, in_array('inspect_vehicle', $opened, true));
    T::same(false, in_array('review_flags', $opened, true));
    T::true(in_array('review_flags', $keys(Rules::activitiesFor('claim.opened', ['loss_type' => 'fire', 'flagged' => true])), true));
    T::same(['medical_report'], $keys(Rules::activitiesFor('exposure.added', ['exposure_type' => 'third_party_injury'])));
    T::same(['transition' => 'investigating'], Rules::onComplete('verify_coverage'));
});

T::test('assignment: specialist in region beats generalist, then least loaded wins', function () {
    $generalist = Fx::adjuster(['region' => 'central']);
    $specialist = Fx::adjuster(['region' => 'central', 'specialty' => 'theft']);
    $other = Fx::adjuster(['region' => 'western', 'specialty' => 'theft']);
    T::same($specialist['id'], Assignment::pick('theft', 'central'));
    T::same($generalist['id'], Assignment::pick('collision', 'central'));
    T::same($other['id'], Assignment::pick('theft', 'western'));
    // nobody in the north: falls through to "any adjuster" (lowest id when loads tie)
    T::same($generalist['id'], Assignment::pick('fire', 'northern'));
});

T::test('assignment: balances workload and ignores suspended adjusters', function () {
    $a = Fx::adjuster(['region' => 'central']);
    $b = Fx::adjuster(['region' => 'central']);
    Fx::adjuster(['region' => 'central', 'status' => 'suspended']);
    $first = Assignment::pick('collision', 'central');
    T::same($a['id'], $first);
    $claimant = Fx::user();
    $policy = Fx::policy($claimant['id']);
    Avic\Services\ClaimService::fnol($policy, Fx::fnolInput(['loss_date' => date('Y-m-d', strtotime('-9 days'))]), $claimant);
    T::same($b['id'], Assignment::pick('collision', 'central'), 'a now has one open claim');
});

T::test('assignment: no adjusters at all leaves the claim unassigned', function () {
    T::same(null, Assignment::pick('collision', 'central'));
});
