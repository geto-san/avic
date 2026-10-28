<?php
declare(strict_types=1);

namespace Avic\Services;

/**
 * The seam to the policy administration system. ClaimCenter talks to PolicyCenter through
 * a search + retrieve pair; here both are answered from the local policies table, but a
 * real integration would replace this class and nothing else.
 */
interface PolicyGateway
{
    public function findByNumber(string $policyNumber): ?array;
}
