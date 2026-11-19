<?php
declare(strict_types=1);

use Avic\Finance\ClaimAmount;
require_once dirname(__DIR__, 2) . '/src/Finance/ClaimAmount.php';

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
function assertPolicyCoverage(PDO $conn, array $claim, string $amount): void
{
    $policy = policyLock($conn, (int)$claim['policy_id']);
    if ($policy['status'] !== 'active' || (int)$policy['user_id'] !== (int)$claim['user_id']) {
        throw new ApiAbort('This claim needs an active policy belonging to its claimant.', 422);
    }
    if (ClaimAmount::units($amount) === 0) {
        throw new ApiAbort('Set a positive amount within the supported currency range.', 422);
    }
    if ($claim['incident_date'] < $policy['start_date'] || $claim['incident_date'] > $policy['end_date']) {
        throw new ApiAbort('The incident falls outside the policy period.', 422);
    }
    if ($policy['coverage_type'] === 'basic' && in_array($claim['claim_type'], ['fire', 'theft', 'natural_disaster'], true)) {
        throw new ApiAbort('This peril is not covered under the basic tier of the policy.', 422);
    }
    // Compare exact SQL decimals, including the aggregate across other claims.
    $query = $conn->prepare(
        'SELECT COALESCE(SUM(approved_amount), 0) + CAST(? AS DECIMAL(12,2)) <= CAST(? AS DECIMAL(12,2))
         FROM claims WHERE policy_id = ? AND id <> ? AND status IN ("approved", "paid", "closed")'
    );
    $query->execute([$amount, $policy['coverage_limit'], $policy['id'], $claim['id']]);
    if (!(bool)$query->fetchColumn()) {
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
