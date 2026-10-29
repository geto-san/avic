<?php
declare(strict_types=1);

namespace Avic\Services;

use Avic\Core\Db;
use Avic\Core\HttpException;
use Avic\Core\RuleViolation;
use Avic\Domain\Catalog;

/**
 * Reserves, payments and recoveries, kept as an append-only ledger.
 *
 *   available reserve (per exposure) = sum(reserve rows) - sum(payment rows)
 *
 * A payment is refused unless the exposure has enough reserve left, so money can only
 * be paid out after it has been set aside. Nothing here ever updates or deletes a row.
 */
final class FinancialService
{
    /** @return list<array<string,mixed>> one row per exposure with reserve / paid / recovered / available */
    public static function totals(int $claimId): array
    {
        $rows = Db::all(
            "SELECT e.id, e.exposure_type, e.party_name, e.status,
                    COALESCE(SUM(CASE WHEN t.type = 'reserve'  THEN t.amount END), 0) AS reserve,
                    COALESCE(SUM(CASE WHEN t.type = 'payment'  THEN t.amount END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN t.type = 'recovery' THEN t.amount END), 0) AS recovered
             FROM exposures e LEFT JOIN transactions t ON t.exposure_id = e.id
             WHERE e.claim_id = ? GROUP BY e.id ORDER BY e.id",
            [$claimId]
        );
        foreach ($rows as &$r) {
            $r['reserve'] = (int)$r['reserve'];
            $r['paid'] = (int)$r['paid'];
            $r['recovered'] = (int)$r['recovered'];
            $r['available'] = $r['reserve'] - $r['paid'];
        }
        return $rows;
    }

    /** @return array{reserve:int,paid:int,recovered:int,available:int} */
    public static function claimTotals(int $claimId): array
    {
        $t = ['reserve' => 0, 'paid' => 0, 'recovered' => 0, 'available' => 0];
        foreach (self::totals($claimId) as $r) {
            foreach ($t as $k => $_) {
                $t[$k] += $r[$k];
            }
        }
        return $t;
    }

    /** @return list<array<string,mixed>> */
    public static function ledger(int $claimId): array
    {
        return Db::all(
            'SELECT t.*, e.party_name, e.exposure_type, u.full_name AS actor_name
             FROM transactions t JOIN exposures e ON e.id = t.exposure_id LEFT JOIN users u ON u.id = t.created_by
             WHERE t.claim_id = ? ORDER BY t.id DESC',
            [$claimId]
        );
    }

    /** Set the TOTAL reserve for an exposure; the ledger records the difference. */
    public static function setReserve(int $claimId, int $exposureId, ?int $newTotal, string $memo, array $actor): void
    {
        self::adjusterOnly($actor);
        Db::tx(function () use ($claimId, $exposureId, $newTotal, $memo, $actor) {
            $claim = self::lockClaim($claimId);
            if ($claim['status'] === 'closed') {
                throw new RuleViolation('Reserves cannot be changed on a closed claim.');
            }
            $exposure = self::openExposure($claimId, $exposureId);
            if ($newTotal === null || $newTotal < 0) {
                throw new RuleViolation('Enter the reserve as a whole number of shillings (0 or more).');
            }
            $row = self::row($claimId, $exposureId);
            if ($newTotal < $row['paid']) {
                throw new RuleViolation('The reserve cannot be lower than the ' . money($row['paid']) . ' already paid on this exposure.');
            }
            $delta = $newTotal - $row['reserve'];
            if ($delta === 0) {
                throw new RuleViolation('That is already the current reserve.');
            }
            $limit = (int)Db::val('SELECT coverage_limit FROM policies WHERE id = ?', [$claim['policy_id']]);
            if (self::claimTotals($claimId)['reserve'] + $delta > $limit) {
                throw new RuleViolation('Total reserves would exceed the policy limit of ' . money($limit) . '.');
            }
            $id = Db::insert(
                "INSERT INTO transactions (claim_id, exposure_id, type, amount, memo, created_by) VALUES (?, ?, 'reserve', ?, ?, ?)",
                [$claimId, $exposureId, $delta, $memo !== '' ? mb_substr($memo, 0, 255) : null, $actor['id']]
            );
            AuditLog::record($claimId, 'financial.reserve_set', $actor, [
                'exposure' => $exposure['party_name'], 'from' => $row['reserve'], 'to' => $newTotal,
            ], 'transaction', $id);
        });
    }

    public static function issuePayment(int $claimId, int $exposureId, ?int $amount, string $payee, string $method, string $reference, array $actor): void
    {
        self::adjusterOnly($actor);
        Db::tx(function () use ($claimId, $exposureId, $amount, $payee, $method, $reference, $actor) {
            $claim = self::lockClaim($claimId);
            if (!in_array($claim['status'], ['investigating', 'settled'], true)) {
                throw new RuleViolation('Payments can only be issued while a claim is being investigated or is settled.');
            }
            if (!empty($claim['flags'])) {
                throw new RuleViolation('This claim has flagged items — complete "Review flagged items" before paying.');
            }
            $exposure = self::openExposure($claimId, $exposureId);
            if ($amount === null || $amount <= 0) {
                throw new RuleViolation('Enter a payment amount greater than zero.');
            }
            if ($payee === '') {
                throw new RuleViolation('Enter who is being paid.');
            }
            if (!isset(Catalog::PAYMENT_METHODS[$method])) {
                throw new RuleViolation('Choose a payment method.');
            }
            $row = self::row($claimId, $exposureId);
            if ($amount > $row['available']) {
                throw new RuleViolation('Insufficient reserve: ' . money($row['available']) . ' is available on this exposure. Raise the reserve first.');
            }
            $limit = (int)Db::val('SELECT coverage_limit FROM policies WHERE id = ?', [$claim['policy_id']]);
            if (self::claimTotals($claimId)['paid'] + $amount > $limit) {
                throw new RuleViolation('This payment would take total payments over the policy limit of ' . money($limit) . '.');
            }
            $id = Db::insert(
                "INSERT INTO transactions (claim_id, exposure_id, type, amount, payee, payment_method, reference, created_by)
                 VALUES (?, ?, 'payment', ?, ?, ?, ?, ?)",
                [$claimId, $exposureId, $amount, mb_substr($payee, 0, 120), $method, $reference !== '' ? mb_substr($reference, 0, 80) : null, $actor['id']]
            );
            if ($reference === '') {
                Db::run('UPDATE transactions SET reference = ? WHERE id = ?', ['PAY-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT), $id]);
            }
            AuditLog::record($claimId, 'financial.payment_issued', $actor, [
                'exposure' => $exposure['party_name'], 'amount' => $amount, 'payee' => $payee, 'method' => $method,
            ], 'transaction', $id);
        });
    }

    /** Money coming back in (subrogation from a liable party, salvage sale). */
    public static function recordRecovery(int $claimId, int $exposureId, ?int $amount, string $source, array $actor): void
    {
        self::adjusterOnly($actor);
        Db::tx(function () use ($claimId, $exposureId, $amount, $source, $actor) {
            $claim = self::lockClaim($claimId);
            if ($claim['status'] === 'open') {
                throw new RuleViolation('Recoveries can be recorded once the investigation has started.');
            }
            $exp = Db::one('SELECT * FROM exposures WHERE id = ? AND claim_id = ?', [$exposureId, $claimId]);
            if (!$exp) {
                throw new RuleViolation('Choose an exposure on this claim.');
            }
            if ($amount === null || $amount <= 0) {
                throw new RuleViolation('Enter a recovery amount greater than zero.');
            }
            if ($source === '') {
                throw new RuleViolation('Say where the recovery came from.');
            }
            $id = Db::insert(
                "INSERT INTO transactions (claim_id, exposure_id, type, amount, payee, created_by) VALUES (?, ?, 'recovery', ?, ?, ?)",
                [$claimId, $exposureId, $amount, mb_substr($source, 0, 120), $actor['id']]
            );
            AuditLog::record($claimId, 'financial.recovery_recorded', $actor, ['amount' => $amount, 'source' => $source], 'transaction', $id);
        });
    }

    /** Close-out: zero every exposure's remaining reserve with an offsetting ledger row. */
    public static function releaseReserves(int $claimId, array $actor): void
    {
        foreach (self::totals($claimId) as $r) {
            if ($r['available'] > 0) {
                Db::insert(
                    "INSERT INTO transactions (claim_id, exposure_id, type, amount, memo, created_by) VALUES (?, ?, 'reserve', ?, 'Reserve released on closure', ?)",
                    [$claimId, $r['id'], -$r['available'], $actor['id']]
                );
            }
        }
    }

    // ---------------------------------------------------------------------------------

    private static function adjusterOnly(array $actor): void
    {
        if ($actor['role'] !== 'adjuster') {
            throw new HttpException(403);
        }
    }

    private static function lockClaim(int $claimId): array
    {
        $c = Db::one('SELECT * FROM claims WHERE id = ? FOR UPDATE', [$claimId]);
        if (!$c) {
            throw new HttpException(404);
        }
        return $c;
    }

    private static function openExposure(int $claimId, int $exposureId): array
    {
        $e = Db::one('SELECT * FROM exposures WHERE id = ? AND claim_id = ?', [$exposureId, $claimId]);
        if (!$e) {
            throw new RuleViolation('Choose an exposure on this claim.');
        }
        if ($e['status'] !== 'open') {
            throw new RuleViolation('That exposure is closed.');
        }
        return $e;
    }

    private static function row(int $claimId, int $exposureId): array
    {
        foreach (self::totals($claimId) as $r) {
            if ((int)$r['id'] === $exposureId) {
                return $r;
            }
        }
        throw new RuleViolation('Choose an exposure on this claim.');
    }
}
