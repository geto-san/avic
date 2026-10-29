<?php
declare(strict_types=1);

namespace Avic\Services;

use Avic\Core\Db;
use Avic\Core\RuleViolation;

final class LocalPolicyGateway implements PolicyGateway
{
    public function findByNumber(string $policyNumber): ?array
    {
        $number = strtoupper(preg_replace('/\s+/', '', $policyNumber) ?? '');
        return $number === '' ? null : Db::one('SELECT * FROM policies WHERE policy_number = ?', [$number]);
    }

    /** The policy a user may report a loss on: claimants only their own, adjusters any. */
    public function findForActor(string $policyNumber, array $actor): ?array
    {
        $p = $this->findByNumber($policyNumber);
        if ($p === null) {
            return null;
        }
        return ($actor['role'] === 'adjuster' || (int)$p['user_id'] === (int)$actor['id']) ? $p : null;
    }

    public function find(int $id): ?array
    {
        return Db::one('SELECT * FROM policies WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        return Db::all('SELECT * FROM policies WHERE user_id = ? ORDER BY end_date DESC', [$userId]);
    }

    /** Attach an unclaimed policy to a claimant, proving ownership with number + plate. */
    public function link(string $policyNumber, string $plate, array $user): array
    {
        $p = $this->findByNumber($policyNumber);
        $plate = strtoupper(preg_replace('/\s+/', '', $plate) ?? '');
        $match = $p !== null
            && strtoupper(preg_replace('/\s+/', '', $p['vehicle_plate']) ?? '') === $plate
            && ($p['user_id'] === null || (int)$p['user_id'] === (int)$user['id']);
        if (!$match) {
            throw new RuleViolation('No policy that can be linked matches those details.');
        }
        Db::run('UPDATE policies SET user_id = ? WHERE id = ?', [$user['id'], $p['id']]);
        AuditLog::record(null, 'policy.linked', $user, ['policy' => $p['policy_number']], 'policy', (int)$p['id']);
        return $p;
    }
}
