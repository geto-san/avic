<?php
declare(strict_types=1);

namespace Avic\Services;

use Avic\Core\Db;
use Avic\Core\HttpException;
use Avic\Core\RuleViolation;
use Avic\Domain\Catalog;
use Avic\Domain\Rules;

/** Tasks on a claim: created by rules or by hand, completed or skipped by adjusters. */
final class ActivityService
{
    /**
     * Create the activities the rules call for. $claim needs id, loss_type, assigned_to, flags.
     * Returns how many were created (an identical open activity is never duplicated).
     */
    public static function spawn(string $event, array $claim, ?array $exposure = null): int
    {
        $ctx = [
            'loss_type' => $claim['loss_type'],
            'exposure_type' => $exposure['exposure_type'] ?? null,
            'flagged' => !empty($claim['flags']),
        ];
        $made = 0;
        foreach (Rules::activitiesFor($event, $ctx) as $tpl) {
            $dupe = Db::val(
                "SELECT id FROM activities WHERE claim_id = ? AND template_key = ? AND status = 'open' AND exposure_id <=> ?",
                [$claim['id'], $tpl['key'], $exposure['id'] ?? null]
            );
            if ($dupe) {
                continue;
            }
            Db::run(
                'INSERT INTO activities (claim_id, exposure_id, template_key, subject, description, priority, mandatory, assigned_to, due_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $claim['id'], $exposure['id'] ?? null, $tpl['key'], $tpl['subject'], $tpl['description'] ?? null,
                    $tpl['priority'], $tpl['mandatory'] ? 1 : 0, $claim['assigned_to'],
                    date('Y-m-d', strtotime('+' . (int)$tpl['due_days'] . ' days')),
                ]
            );
            $made++;
        }
        return $made;
    }

    /** A manual activity. */
    public static function create(int $claimId, array $in, array $actor): int
    {
        self::adjusterOnly($actor);
        return (int)Db::tx(function () use ($claimId, $in, $actor) {
            $claim = ClaimService::lock($claimId);
            if ($claim['status'] === 'closed') {
                throw new RuleViolation('Reopen the claim before adding activities.');
            }
            $errors = [];
            $subject = trim((string)($in['subject'] ?? ''));
            if ($subject === '') {
                $errors['subject'] = 'Give the activity a subject.';
            }
            $priority = (string)($in['priority'] ?? 'normal');
            if (!isset(Catalog::PRIORITIES[$priority])) {
                $errors['priority'] = 'Choose a priority.';
            }
            $due = $in['due_date'] ?? null;
            if (!$due) {
                $errors['due_date'] = 'Choose a due date.';
            }
            if ($errors) {
                throw new \Avic\Core\ValidationException($errors);
            }
            $assignee = $in['assigned_to'] ?? $claim['assigned_to'];
            if ($assignee !== null && !Db::val("SELECT id FROM users WHERE id = ? AND role = 'adjuster' AND status = 'active'", [$assignee])) {
                throw new RuleViolation('Choose an active adjuster.');
            }
            $id = Db::insert(
                'INSERT INTO activities (claim_id, subject, description, priority, assigned_to, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$claimId, mb_substr($subject, 0, 150), mb_substr((string)($in['description'] ?? ''), 0, 500) ?: null, $priority, $assignee, $due, $actor['id']]
            );
            AuditLog::record($claimId, 'activity.created', $actor, ['subject' => $subject], 'activity', $id);
            return $id;
        });
    }

    public static function complete(int $activityId, array $actor): int
    {
        self::adjusterOnly($actor);
        return (int)Db::tx(function () use ($activityId, $actor) {
            $claimId = (int)Db::val('SELECT claim_id FROM activities WHERE id = ?', [$activityId]);
            if (!$claimId) {
                throw new HttpException(404);
            }
            $claim = ClaimService::lock($claimId);
            $a = Db::one('SELECT * FROM activities WHERE id = ? FOR UPDATE', [$activityId]);
            if ($a['status'] !== 'open') {
                throw new RuleViolation('This activity is already ' . $a['status'] . '.');
            }
            Db::run("UPDATE activities SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ?", [$actor['id'], $activityId]);
            AuditLog::record($claimId, 'activity.completed', $actor, ['subject' => $a['subject']], 'activity', $activityId);

            $then = Rules::onComplete($a['template_key']);
            if (!empty($then['clear_flags'])) {
                Db::run('UPDATE claims SET flags = NULL WHERE id = ?', [$claimId]);
                AuditLog::record($claimId, 'claim.flags_cleared', $actor, [], 'claim', $claimId);
            }
            if (!empty($then['transition']) && $claim['status'] === 'open') {
                ClaimService::transition($claimId, $then['transition'], $actor);
            }
            return $claimId;
        });
    }

    public static function skip(int $activityId, array $actor): int
    {
        self::adjusterOnly($actor);
        return (int)Db::tx(function () use ($activityId, $actor) {
            $claimId = (int)Db::val('SELECT claim_id FROM activities WHERE id = ?', [$activityId]);
            if (!$claimId) {
                throw new HttpException(404);
            }
            ClaimService::lock($claimId);
            $a = Db::one('SELECT * FROM activities WHERE id = ? FOR UPDATE', [$activityId]);
            if ($a['status'] !== 'open') {
                throw new RuleViolation('This activity is already ' . $a['status'] . '.');
            }
            if ($a['mandatory']) {
                throw new RuleViolation('Mandatory activities must be completed, not skipped.');
            }
            Db::run("UPDATE activities SET status = 'skipped', completed_by = ?, completed_at = NOW() WHERE id = ?", [$actor['id'], $activityId]);
            AuditLog::record($claimId, 'activity.skipped', $actor, ['subject' => $a['subject']], 'activity', $activityId);
            return $claimId;
        });
    }

    /** @return list<array<string,mixed>> */
    public static function forClaim(int $claimId): array
    {
        return Db::all(
            "SELECT a.*, u.full_name AS assignee_name, e.party_name
             FROM activities a LEFT JOIN users u ON u.id = a.assigned_to LEFT JOIN exposures e ON e.id = a.exposure_id
             WHERE a.claim_id = ?
             ORDER BY (a.status = 'open') DESC, a.due_date ASC, a.id ASC",
            [$claimId]
        );
    }

    /** @return list<array<string,mixed>> open activities for an adjuster, soonest first */
    public static function openFor(int $userId, ?int $limit = null): array
    {
        return Db::all(
            "SELECT a.*, c.claim_number, p.insured_name
             FROM activities a JOIN claims c ON c.id = a.claim_id JOIN policies p ON p.id = c.policy_id
             WHERE a.assigned_to = ? AND a.status = 'open'
             ORDER BY a.due_date ASC, FIELD(a.priority, 'urgent','high','normal','low'), a.id" . ($limit ? ' LIMIT ' . (int)$limit : ''),
            [$userId]
        );
    }

    private static function adjusterOnly(array $actor): void
    {
        if ($actor['role'] !== 'adjuster') {
            throw new HttpException(403);
        }
    }
}
