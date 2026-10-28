<?php
declare(strict_types=1);

namespace Avic\Services;

use Avic\Core\Db;
use Avic\Core\HttpException;
use Avic\Core\RuleViolation;
use Avic\Core\ValidationException;
use Avic\Domain\Assignment;
use Avic\Domain\Catalog;
use Avic\Domain\ClaimLifecycle;
use Avic\Domain\CoverageCheck;

final class ClaimService
{
    // --- reading ---------------------------------------------------------------------

    /** Claim + policy + assignee. A claimant asking for someone else's claim gets a 404. */
    public static function get(int $id, array $viewer): array
    {
        $c = Db::one(
            "SELECT c.*, p.policy_number, p.insured_name, p.user_id AS policy_user_id, p.vehicle_make, p.vehicle_model,
                    p.vehicle_year, p.vehicle_plate, p.coverage_type, p.coverage_limit, p.start_date, p.end_date,
                    p.status AS policy_status, a.full_name AS assignee_name, r.full_name AS reporter_name
             FROM claims c JOIN policies p ON p.id = c.policy_id
             LEFT JOIN users a ON a.id = c.assigned_to LEFT JOIN users r ON r.id = c.reported_by
             WHERE c.id = ?",
            [$id]
        );
        if (!$c || ($viewer['role'] === 'claimant' && (int)$c['policy_user_id'] !== (int)$viewer['id'])) {
            throw new HttpException(404);
        }
        return $c;
    }

    /** @return list<string> */
    public static function flags(array $claim): array
    {
        return $claim['flags'] ? (json_decode($claim['flags'], true) ?: []) : [];
    }

    /** Re-read a claim and hold its row lock until the surrounding transaction ends. */
    public static function lock(int $id): array
    {
        $c = Db::one('SELECT * FROM claims WHERE id = ? FOR UPDATE', [$id]);
        if (!$c) {
            throw new HttpException(404);
        }
        return $c;
    }

    /**
     * @param array{view?:string,status?:string,q?:string,page?:int} $f
     * @return array{rows:list<array<string,mixed>>,total:int,pages:int,page:int}
     */
    public static function search(array $f, array $viewer, int $perPage = 15): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($viewer['role'] === 'claimant') {
            $where[] = 'p.user_id = ?';
            $params[] = $viewer['id'];
        } else {
            $view = $f['view'] ?? 'mine';
            if ($view === 'mine') {
                $where[] = 'c.assigned_to = ?';
                $params[] = $viewer['id'];
            } elseif ($view === 'unassigned') {
                $where[] = "c.assigned_to IS NULL AND c.status <> 'closed'";
            }
        }
        if (!empty($f['status']) && isset(Catalog::STATUSES[$f['status']])) {
            $where[] = 'c.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['q'])) {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $where[] = '(c.claim_number LIKE ? OR p.insured_name LIKE ? OR p.vehicle_plate LIKE ? OR p.policy_number LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $from = 'FROM claims c JOIN policies p ON p.id = c.policy_id LEFT JOIN users a ON a.id = c.assigned_to WHERE ' . implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) ' . $from, $params);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, (int)($f['page'] ?? 1)), $pages);
        $rows = Db::all(
            'SELECT c.id, c.claim_number, c.loss_date, c.loss_type, c.status, c.close_outcome, c.flags, c.created_at,
                    p.insured_name, p.vehicle_plate, a.full_name AS assignee_name ' . $from .
            ' ORDER BY c.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return array<string,int> */
    public static function adjusterStats(int $userId): array
    {
        return [
            'mine' => (int)Db::val("SELECT COUNT(*) FROM claims WHERE assigned_to = ? AND status <> 'closed'", [$userId]),
            'unassigned' => (int)Db::val("SELECT COUNT(*) FROM claims WHERE assigned_to IS NULL AND status <> 'closed'"),
            'activities' => (int)Db::val("SELECT COUNT(*) FROM activities WHERE assigned_to = ? AND status = 'open'", [$userId]),
            'overdue' => (int)Db::val("SELECT COUNT(*) FROM activities WHERE assigned_to = ? AND status = 'open' AND due_date < CURDATE()", [$userId]),
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function exposures(int $claimId): array
    {
        return Db::all('SELECT * FROM exposures WHERE claim_id = ? ORDER BY id', [$claimId]);
    }

    // --- first notice of loss --------------------------------------------------------

    /**
     * @param array<string,mixed> $in loss_date, loss_type, loss_region, loss_location, description,
     *        police_ref, exposures: list of [type, party, description]
     * @return array<string,string> field => message
     */
    public static function validateFnol(array $policy, array $in): array
    {
        $e = [];
        $date = $in['loss_date'] ?? null;
        if (!$date) {
            $e['loss_date'] = 'Enter the date of loss.';
        } elseif ($date > date('Y-m-d')) {
            $e['loss_date'] = 'The date of loss cannot be in the future.';
        }
        if (!isset(Catalog::LOSS_TYPES[$in['loss_type'] ?? ''])) {
            $e['loss_type'] = 'Choose what happened.';
        }
        if (!isset(Catalog::REGIONS[$in['loss_region'] ?? ''])) {
            $e['loss_region'] = 'Choose the region where it happened.';
        }
        if (trim((string)($in['loss_location'] ?? '')) === '') {
            $e['loss_location'] = 'Say where it happened.';
        }
        if (mb_strlen(trim((string)($in['description'] ?? ''))) < 10) {
            $e['description'] = 'Describe what happened (at least a sentence).';
        }
        $exposures = $in['exposures'] ?? [];
        if (!$exposures) {
            $e['exposures'] = 'Add at least one exposure (what was damaged or who was hurt).';
        }
        foreach ($exposures as $x) {
            if (!isset(Catalog::EXPOSURE_TYPES[$x['type'] ?? ''])) {
                $e['exposures'] = 'Choose a type for every exposure.';
            } elseif (trim((string)($x['party'] ?? '')) === '') {
                $e['exposures'] = 'Name the owner or injured person for every exposure.';
            } elseif ($problem = CoverageCheck::exposureProblem($policy, $x['type'])) {
                $e['exposures'] = $problem;
            }
        }
        return $e;
    }

    /** Create the claim, its exposures, flags, assignment and first activities — atomically. */
    public static function fnol(array $policy, array $in, array $actor): int
    {
        $errors = self::validateFnol($policy, $in);
        if ($errors) {
            throw new ValidationException($errors);
        }
        return (int)Db::tx(function () use ($policy, $in, $actor) {
            $id = Db::insert(
                'INSERT INTO claims (claim_number, policy_id, reported_by, loss_date, loss_type, loss_region, loss_location, description, police_ref)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'PENDING-' . bin2hex(random_bytes(8)), $policy['id'], $actor['id'], $in['loss_date'], $in['loss_type'],
                    $in['loss_region'], mb_substr(trim($in['loss_location']), 0, 255), trim($in['description']),
                    ($in['police_ref'] ?? '') !== '' ? mb_substr($in['police_ref'], 0, 60) : null,
                ]
            );
            $number = 'CLM-' . date('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

            $flags = CoverageCheck::flags($policy, $in['loss_date']);
            $dupe = Db::val(
                'SELECT claim_number FROM claims WHERE policy_id = ? AND loss_date = ? AND loss_type = ? AND id <> ? ORDER BY id LIMIT 1',
                [$policy['id'], $in['loss_date'], $in['loss_type'], $id]
            );
            if ($dupe) {
                $flags[] = "Possible duplicate of $dupe (same policy, date and type of loss).";
            }
            $assignee = Assignment::pick($in['loss_type'], $in['loss_region']);
            Db::run(
                'UPDATE claims SET claim_number = ?, flags = ?, assigned_to = ? WHERE id = ?',
                [$number, $flags ? json_encode($flags) : null, $assignee, $id]
            );
            AuditLog::record($id, 'claim.created', $actor, ['claim_number' => $number, 'policy' => $policy['policy_number']], 'claim', $id);
            if ($assignee) {
                AuditLog::record($id, 'claim.assigned', null, ['to' => $assignee, 'by' => 'assignment rules'], 'claim', $id);
            }
            foreach ($flags as $f) {
                AuditLog::record($id, 'claim.flagged', null, ['reason' => $f], 'claim', $id);
            }

            $claim = Db::one('SELECT * FROM claims WHERE id = ?', [$id]);
            ActivityService::spawn('claim.opened', $claim);
            foreach ($in['exposures'] as $x) {
                self::insertExposure($claim, $x, $actor);
            }
            return $id;
        });
    }

    // --- changing a claim ------------------------------------------------------------

    public static function addExposure(int $claimId, array $x, array $actor): void
    {
        self::adjusterOnly($actor);
        Db::tx(function () use ($claimId, $x, $actor) {
            $claim = self::lock($claimId);
            if ($claim['status'] === 'closed') {
                throw new RuleViolation('Reopen the claim before adding exposures.');
            }
            $policy = Db::one('SELECT * FROM policies WHERE id = ?', [$claim['policy_id']]);
            if (!isset(Catalog::EXPOSURE_TYPES[$x['type'] ?? ''])) {
                throw new RuleViolation('Choose an exposure type.');
            }
            if (trim((string)($x['party'] ?? '')) === '') {
                throw new RuleViolation('Name the owner or injured person.');
            }
            if ($problem = CoverageCheck::exposureProblem($policy, $x['type'])) {
                throw new RuleViolation($problem);
            }
            self::insertExposure($claim, $x, $actor);
        });
    }

    private static function insertExposure(array $claim, array $x, array $actor): void
    {
        $id = Db::insert(
            'INSERT INTO exposures (claim_id, exposure_type, party_name, description) VALUES (?, ?, ?, ?)',
            [$claim['id'], $x['type'], mb_substr(trim($x['party']), 0, 100), mb_substr(trim((string)($x['description'] ?? '')), 0, 500) ?: null]
        );
        AuditLog::record((int)$claim['id'], 'exposure.added', $actor, ['type' => $x['type'], 'party' => $x['party']], 'exposure', $id);
        ActivityService::spawn('exposure.added', $claim, ['id' => $id, 'exposure_type' => $x['type']]);
    }

    /** Assign to an adjuster (or null to put the claim back in the unassigned queue). */
    public static function assign(int $claimId, ?int $assigneeId, array $actor): void
    {
        self::adjusterOnly($actor);
        Db::tx(function () use ($claimId, $assigneeId, $actor) {
            $claim = self::lock($claimId);
            if ($claim['status'] === 'closed') {
                throw new RuleViolation('A closed claim cannot be reassigned.');
            }
            if ($assigneeId !== null && !Db::val("SELECT id FROM users WHERE id = ? AND role = 'adjuster' AND status = 'active'", [$assigneeId])) {
                throw new RuleViolation('Choose an active adjuster.');
            }
            if ((int)$claim['assigned_to'] === (int)$assigneeId) {
                throw new RuleViolation('The claim is already assigned that way.');
            }
            Db::run('UPDATE claims SET assigned_to = ? WHERE id = ?', [$assigneeId, $claimId]);
            Db::run("UPDATE activities SET assigned_to = ? WHERE claim_id = ? AND status = 'open'", [$assigneeId, $claimId]);
            AuditLog::record($claimId, 'claim.assigned', $actor, ['from' => $claim['assigned_to'], 'to' => $assigneeId], 'claim', $claimId);
        });
    }

    /**
     * Move a claim along the lifecycle. Takes the row lock, checks the transition table,
     * then applies the guards for the target status.
     */
    public static function transition(int $claimId, string $to, array $actor, ?string $outcome = null): void
    {
        self::adjusterOnly($actor);
        Db::tx(function () use ($claimId, $to, $actor, $outcome) {
            $claim = self::lock($claimId);
            $from = $claim['status'];
            if (!ClaimLifecycle::canMove($from, $to)) {
                throw new RuleViolation('A ' . $from . ' claim cannot move to ' . $to . '.');
            }
            $totals = FinancialService::claimTotals($claimId);

            if ($to === 'settled') {
                if ($totals['reserve'] <= 0) {
                    throw new RuleViolation('Set a reserve on at least one exposure before settling.');
                }
                if ($claim['flags']) {
                    throw new RuleViolation('Complete "Review flagged items" before settling.');
                }
            }
            if ($to === 'closed') {
                if (!in_array($outcome, ClaimLifecycle::outcomesFor($from), true)) {
                    throw new RuleViolation('Choose how the claim is closed: ' . implode(' or ', ClaimLifecycle::outcomesFor($from)) . '.');
                }
                if ($outcome === 'paid' && $totals['paid'] <= 0) {
                    throw new RuleViolation('A claim cannot be closed as paid before any payment has been issued.');
                }
                if ($outcome !== 'paid' && $totals['paid'] > 0) {
                    throw new RuleViolation('Payments have been issued — settle the claim and close it as paid.');
                }
            }

            $set = ['status = ?'];
            $params = [$to];
            if ($to === 'closed') {
                $set[] = 'close_outcome = ?';
                $set[] = 'closed_at = NOW()';
                $params[] = $outcome;
            } elseif ($from === 'closed') {
                $set[] = 'close_outcome = NULL';
                $set[] = 'closed_at = NULL';
            }
            $params[] = $claimId;
            Db::run('UPDATE claims SET ' . implode(', ', $set) . ' WHERE id = ?', $params);

            if ($to === 'closed') {
                FinancialService::releaseReserves($claimId, $actor);
                Db::run("UPDATE exposures SET status = 'closed' WHERE claim_id = ?", [$claimId]);
                Db::run("UPDATE activities SET status = 'skipped', completed_at = NOW(), completed_by = ? WHERE claim_id = ? AND status = 'open'", [$actor['id'], $claimId]);
            }
            if ($from === 'closed') {
                Db::run("UPDATE exposures SET status = 'open' WHERE claim_id = ?", [$claimId]);
            }
            AuditLog::record($claimId, 'claim.status_changed', $actor, ['from' => $from, 'to' => $to, 'outcome' => $outcome], 'claim', $claimId);

            $fresh = Db::one('SELECT * FROM claims WHERE id = ?', [$claimId]);
            if ($to === 'investigating') {
                ActivityService::spawn('claim.investigating', $fresh);
            } elseif ($from === 'closed') {
                ActivityService::spawn('claim.reopened', $fresh);
            }
        });
    }

    private static function adjusterOnly(array $actor): void
    {
        if ($actor['role'] !== 'adjuster') {
            throw new HttpException(403);
        }
    }
}
