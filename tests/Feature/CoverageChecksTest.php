<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

final class CoverageChecksTest extends FeatureTestCase
{
    private const PATH = 'config/api/coverage-checks.php';

    public function testSupervisionDoesNotGrantCompletionAndSuspensionRevokesAccess(): void
    {
        $owner = $this->signIn(self::BRIAN);
        $supervisor = $this->signIn(self::GRACE);
        $request = ['claim_id' => 101, 'evidence' => 'Checked policy schedule and loss date.'];
        $this->assertStatus(403, $this->signIn(self::DOREEN)->get(self::PATH));
        $pending = static fn($client) => array_map('intval', array_column($client->get(self::PATH)->json()['tasks'], 'id'));
        $this->assertNotContains(101, $pending($supervisor));
        $this->db()->exec("INSERT INTO staff_permissions VALUES (8, 'coverage.view_all')");
        $this->assertContains(101, $pending($supervisor));
        $this->assertStatus(403, $supervisor->postJson(self::PATH, $request));
        $this->assertStatus(200, $owner->postJson(self::PATH, $request));
        $this->assertStatus(409, $owner->postJson(self::PATH, $request));
        $this->assertNotContains(101, $pending($supervisor));
        $this->assertSame($request['evidence'], $this->value('SELECT evidence FROM coverage_checks WHERE claim_id = 101'));
        $this->db()->exec("UPDATE users SET status = 'suspended' WHERE id = 8");
        $this->assertStatus(403, $supervisor->get(self::PATH));
    }

    public function testApprovalRetainsTwoDecimalPlacesAndRejectsSilentRounding(): void
    {
        $handler = $this->signIn(self::BRIAN);
        $approve = ['claim_id' => 101, 'decision' => 'approve'];
        $this->assertStatus(422, $handler->postJson('config/api/claims-decision.php', $approve + ['amount' => '10.001']));
        $this->assertStatus(200, $handler->postJson('config/api/claims-decision.php', $approve + ['amount' => '10.07']));
        $this->assertSame('10.07', $this->value('SELECT amount FROM payouts WHERE claim_id = 101'));
    }
}
