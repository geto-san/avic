<?php
declare(strict_types=1);

use Avic\Core\Db;
use Avic\Core\RuleViolation;
use Avic\Services\ClaimService;
use Avic\Services\FinancialService;

/** A claim already under investigation, ready for money: [claimId, adjuster, exposureId]. */
function investigating(array $claimOver = []): array
{
    [$id, $adj] = Fx::claim($claimOver);
    Fx::investigate($id, $adj);
    return [$id, $adj, Fx::exposureId($id)];
}

T::test('ledger: reserve, payment and recovery add up per exposure', function () {
    [$id, $adj, $x] = investigating();
    FinancialService::setReserve($id, $x, 5000000, 'Initial estimate', $adj);
    FinancialService::issuePayment($id, $x, 3000000, 'Body shop', 'cheque', 'CHQ-77', $adj);
    FinancialService::recordRecovery($id, $x, 500000, 'Salvage sale', $adj);
    T::same(['reserve' => 5000000, 'paid' => 3000000, 'recovered' => 500000, 'available' => 2000000], FinancialService::claimTotals($id));
    T::same('CHQ-77', Db::val("SELECT reference FROM transactions WHERE type = 'payment'"));
});

T::test('ledger: a payment above the available reserve is refused and nothing is written', function () {
    [$id, $adj, $x] = investigating();
    FinancialService::setReserve($id, $x, 1000000, '', $adj);
    FinancialService::issuePayment($id, $x, 800000, 'Garage', 'bank_transfer', '', $adj);
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $x, 300000, 'Garage', 'bank_transfer', '', $adj), 'Insufficient reserve');
    T::same(1, (int)Db::val("SELECT COUNT(*) FROM transactions WHERE type = 'payment'"));
    T::same(200000, FinancialService::claimTotals($id)['available']);
});

T::test('ledger: reserves are per exposure — money cannot be borrowed across exposures', function () {
    [$id, $adj, $x] = investigating();
    ClaimService::addExposure($id, ['type' => 'third_party_vehicle', 'party' => 'Other driver'], $adj);
    $y = (int)Db::val('SELECT MAX(id) FROM exposures WHERE claim_id = ?', [$id]);
    FinancialService::setReserve($id, $x, 2000000, '', $adj);
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $y, 1000, 'Other driver', 'cheque', '', $adj), 'Insufficient reserve');
});

T::test('ledger: the reserve cannot drop below what has been paid; lowering is a negative ledger row', function () {
    [$id, $adj, $x] = investigating();
    FinancialService::setReserve($id, $x, 2000000, '', $adj);
    FinancialService::issuePayment($id, $x, 1500000, 'Garage', 'cheque', '', $adj);
    T::throws(RuleViolation::class, fn() => FinancialService::setReserve($id, $x, 1000000, '', $adj), 'already paid');
    FinancialService::setReserve($id, $x, 1600000, 'Reduced after negotiation', $adj);
    T::same(-400000, (int)Db::val("SELECT amount FROM transactions WHERE type = 'reserve' ORDER BY id DESC LIMIT 1"));
    T::throws(RuleViolation::class, fn() => FinancialService::setReserve($id, $x, 1600000, '', $adj), 'already the current');
});

T::test('ledger: total reserves and total payments are capped by the policy limit', function () {
    $adj = Fx::adjuster(['region' => 'central']);
    $claimant = Fx::user();
    $policy = Fx::policy($claimant['id'], ['coverage_limit' => 1000000]);
    $id = ClaimService::fnol($policy, Fx::fnolInput(), $claimant);
    Fx::investigate($id, $adj);
    $x = Fx::exposureId($id);
    T::throws(RuleViolation::class, fn() => FinancialService::setReserve($id, $x, 1000001, '', $adj), 'policy limit');
    FinancialService::setReserve($id, $x, 1000000, '', $adj);
    FinancialService::issuePayment($id, $x, 1000000, 'Garage', 'cheque', '', $adj);
});

T::test('ledger: payments need an investigating/settled claim; reserves are refused once closed', function () {
    [$id, $adj] = Fx::claim();
    $x = Fx::exposureId($id);
    FinancialService::setReserve($id, $x, 500000, '', $adj);   // allowed while open
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $x, 1000, 'Garage', 'cheque', '', $adj), 'investigated');
    T::throws(RuleViolation::class, fn() => FinancialService::recordRecovery($id, $x, 1000, 'Salvage', $adj), 'investigation');
    ClaimService::transition($id, 'closed', $adj, 'withdrawn');
    T::throws(RuleViolation::class, fn() => FinancialService::setReserve($id, $x, 900000, '', $adj), 'closed claim');
});

T::test('ledger: input validation on payments', function () {
    [$id, $adj, $x] = investigating();
    FinancialService::setReserve($id, $x, 1000000, '', $adj);
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $x, null, 'Garage', 'cheque', '', $adj), 'greater than zero');
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $x, -5, 'Garage', 'cheque', '', $adj), 'greater than zero');
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $x, 100, '', 'cheque', '', $adj), 'who is being paid');
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, $x, 100, 'Garage', 'bitcoin', '', $adj), 'payment method');
    T::throws(RuleViolation::class, fn() => FinancialService::issuePayment($id, 999999, 100, 'Garage', 'cheque', '', $adj), 'exposure');
});

T::test('ledger: the database itself refuses a zero or negative payment row', function () {
    [$id, $adj, $x] = investigating();
    T::throws(PDOException::class, fn() => Db::run("INSERT INTO transactions (claim_id, exposure_id, type, amount) VALUES (?, ?, 'payment', -10)", [$id, $x]));
    T::throws(PDOException::class, fn() => Db::run("INSERT INTO transactions (claim_id, exposure_id, type, amount) VALUES (?, ?, 'reserve', 0)", [$id, $x]));
});

T::test('ledger: every money movement is audited with the actor', function () {
    [$id, $adj, $x] = investigating();
    FinancialService::setReserve($id, $x, 700000, '', $adj);
    FinancialService::issuePayment($id, $x, 100000, 'Garage', 'mobile_money', '', $adj);
    $actions = array_column(Db::all("SELECT action FROM audit_log WHERE claim_id = ? AND action LIKE 'financial.%' ORDER BY id", [$id]), 'action');
    T::same(['financial.reserve_set', 'financial.payment_issued'], $actions);
    T::same($adj['id'], (int)Db::val("SELECT user_id FROM audit_log WHERE action = 'financial.payment_issued'"));
    T::same('PAY-' . str_pad((string)Db::val("SELECT id FROM transactions WHERE type = 'payment'"), 6, '0', STR_PAD_LEFT), Db::val("SELECT reference FROM transactions WHERE type = 'payment'"));
});
