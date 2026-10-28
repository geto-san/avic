<?php
declare(strict_types=1);

namespace Avic\Domain;

/** Automated coverage rules applied at first notice of loss. Pure functions — no database. */
final class CoverageCheck
{
    /**
     * Items an adjuster must look at before any money moves. A claim is still accepted
     * (as in ClaimCenter) — the questions are flagged rather than blocking intake.
     *
     * @return list<string>
     */
    public static function flags(array $policy, string $lossDate): array
    {
        $flags = [];
        if ($policy['status'] !== 'active') {
            $flags[] = 'The policy is ' . $policy['status'] . '.';
        }
        if ($lossDate < $policy['start_date'] || $lossDate > $policy['end_date']) {
            $flags[] = 'The loss date (' . $lossDate . ') is outside the policy period ('
                . $policy['start_date'] . ' to ' . $policy['end_date'] . ').';
        }
        return $flags;
    }

    /** Third-party policies never pay for the insured's own vehicle. Returns an error or null. */
    public static function exposureProblem(array $policy, string $exposureType): ?string
    {
        if ($policy['coverage_type'] === 'third_party' && $exposureType === 'own_vehicle') {
            return 'This is a third-party policy: damage to the insured vehicle is not covered.';
        }
        return null;
    }
}
