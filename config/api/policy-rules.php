<?php
declare(strict_types=1);

/** Policy locking serializes submissions and approvals that share one cover limit. */
function policyLock(PDO $conn, int $policyId): array
{
    $query = $conn->prepare('SELECT * FROM policies WHERE id = ? FOR UPDATE');
    $query->execute([$policyId]);
    $policy = $query->fetch();
    if (!$policy) throw new ApiAbort('Policy not found.', 422);
    return $policy;
}

function validDate(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/** Reserve cover for approved claims, including paid/closed settlements. */
function assertPolicyCoverage(PDO $conn, array $claim, float $amount): void
{
    $policy = policyLock($conn, (int)$claim['policy_id']);
    if ($policy['status'] !== 'active' || (int)$policy['user_id'] !== (int)$claim['user_id']) {
        throw new ApiAbort('This claim needs an active policy belonging to its claimant.', 422);
    }
    if (!is_finite($amount) || round($amount, 2) <= 0 || $amount > 9999999999.99) {
        throw new ApiAbort('Set a positive amount within the supported currency range.', 422);
    }
    if ($claim['incident_date'] < $policy['start_date'] || $claim['incident_date'] > $policy['end_date']) {
        throw new ApiAbort('The incident falls outside the policy period.', 422);
    }
    if ($policy['coverage_type'] === 'basic' && in_array($claim['claim_type'], ['fire', 'theft', 'natural_disaster'], true)) {
        throw new ApiAbort('This peril is not covered under the basic tier of the policy.', 422);
    }
    $query = $conn->prepare(
        'SELECT COALESCE(SUM(approved_amount), 0) FROM claims
         WHERE policy_id = ? AND id <> ? AND status IN ("approved", "paid", "closed")'
    );
    $query->execute([$policy['id'], $claim['id']]);
    $reserved = (int)round((float)$query->fetchColumn() * 100);
    $limit = (int)round((float)$policy['coverage_limit'] * 100);
    if ((int)round($amount * 100) + $reserved > $limit) {
        throw new ApiAbort('The amount exceeds the remaining cover limit on this policy.', 422);
    }
}

/** Exact incident match after normalizing case and whitespace in the location. */
function assertNoDuplicateIncident(PDO $conn, array $fields, int $existingId): void
{
    $normalize = static fn(string $location): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $location) ?? $location));
    $query = $conn->prepare(
        'SELECT incident_location FROM claims WHERE policy_id = ? AND incident_date = ?
         AND claim_type = ? AND id <> ? AND status NOT IN ("draft", "rejected")
         AND NOT (status = "closed" AND approved_amount IS NULL)'
    );
    $query->execute([$fields['policy_id'], $fields['incident_date'], $fields['claim_type'], $existingId]);
    foreach ($query->fetchAll() as $other) {
        if ($normalize((string)$other['incident_location']) === $normalize((string)$fields['incident_location'])) {
            throw new ApiAbort('A claim already exists for this policy, incident date, type and location.', 409);
        }
    }
}
