<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

use Avic\Tests\Support\ApiClient;
use Avic\Tests\Support\TestServer;

final class HardeningTest extends FeatureTestCase
{
    public function testAdjusterReceivesPayoutsOnlyForClaimsOnTheirDesk(): void
    {
        $brian = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $brian->postJson('config/api/claims-decision.php', ['claim_id' => 101, 'decision' => 'approve', 'amount' => 5000000]));
        $data = $brian->get('config/api/bootstrap.php')->json();
        $claimIds = array_column($data['payouts'], 'claim_id');
        $this->assertContains(101, $claimIds);
        $this->assertNotContains(107, $claimIds);
        $this->assertSame([], $this->signIn(self::KIGONGO)->get('config/api/bootstrap.php')->json()['payouts']);
    }

    public function testGarageCannotDownloadPrivatePdfEvenForItsOwnJob(): void
    {
        foreach ([self::KIGONGO, self::SSEMWANGA] as $email) {
            $response = $this->signIn($email)->get('config/api/claims-pdf.php?claim_id=101');
            $this->assertStatus(403, $response);
            $this->assertStringNotContainsString('doreen', strtolower($response->body));
        }
    }

    public function testWritesRequireTheCallersCsrfTokenIncludingUploadsAndLogout(): void
    {
        $client = $this->signIn(self::DOREEN);
        $otherToken = $this->anonymous()->csrfToken();
        foreach (['', 'forged', $otherToken] as $token) {
            $this->assertStatus(403, $client->postJson('config/api/claims.php', $this->claimForm(), $token));
            $this->assertStatus(403, $client->postMultipart('config/api/claims.php', $this->claimForm(), [], $token));
            $this->assertStatus(403, $client->postJson('config/auth/logout.php', [], $token));
        }
        $this->assertStatus(200, $client->get('config/api/bootstrap.php'));
        $this->assertStatus(200, $client->postJson('config/api/claims.php', $this->claimForm()));
        $this->assertStatus(405, $client->get('config/auth/logout.php'));
    }

    public function testAuthFormsAlsoRequireCsrf(): void
    {
        foreach (['login', 'register', 'forgot-password', 'reset-password'] as $endpoint) {
            $this->assertStatus(403, $this->anonymous()->postJson('config/auth/' . $endpoint . '.php', [], ''));
        }
    }

    public function testDuplicateIncidentsAreRefusedButDistinctLocationsAreAllowed(): void
    {
        $client = $this->signIn(self::DOREEN);
        $this->assertStatus(200, $client->postJson('config/api/claims.php', $this->claimForm()));
        $this->assertStatus(409, $client->postJson('config/api/claims.php', $this->claimForm(['incident_location' => '  KAMPALA   road '])));
        $this->assertStatus(200, $client->postJson('config/api/claims.php', $this->claimForm(['incident_location' => 'Jinja Road'])));
    }

    public function testSimultaneousDuplicateSubmissionsCreateOneClaim(): void
    {
        if (!TestServer::concurrent()) $this->markTestSkipped('Requires concurrent PHP workers.');
        $clients = [$this->signIn(self::DOREEN), $this->signIn(self::DOREEN), $this->signIn(self::DOREEN)];
        $before = $this->rows('claims');
        $responses = ApiClient::postJsonTogether($clients, 'config/api/claims.php', $this->claimForm());
        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame([200, 409, 409], $statuses, json_encode($responses));
        $this->assertSame($before + 1, $this->rows('claims'));
    }

    public function testApprovalsReserveRemainingCoverAcrossClaimsAndPayoutsRecheckIt(): void
    {
        $reserved = (float)$this->value('SELECT SUM(approved_amount) FROM claims WHERE policy_id = 1 AND status IN ("approved", "paid", "closed")');
        $this->db()->prepare('UPDATE policies SET coverage_limit = ? WHERE id = 1')->execute([$reserved + 5000000]);
        $brian = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $brian->postJson('config/api/claims-decision.php', ['claim_id' => 101, 'decision' => 'approve', 'amount' => 5000000]));
        $this->db()->exec('UPDATE claims SET status = "submitted", adjuster_id = 2 WHERE id = 103');
        $this->assertStatus(422, $brian->postJson('config/api/claims-decision.php', ['claim_id' => 103, 'decision' => 'approve', 'amount' => 1]));
        $this->assertSame('submitted', $this->claimStatus(103));
        $this->db()->exec('UPDATE policies SET coverage_limit = 1 WHERE id = 1');
        $payout = (int)$this->value('SELECT id FROM payouts WHERE claim_id = 101');
        $this->assertStatus(422, $brian->postJson('config/api/payouts-update.php', ['payout_id' => $payout, 'action' => 'mark_processing']));
        $this->assertSame('pending', $this->value('SELECT status FROM payouts WHERE id = ?', [$payout]));
    }

    public function testConcurrentApprovalsCannotOverspendOnePolicy(): void
    {
        if (!TestServer::concurrent()) $this->markTestSkipped('Requires concurrent PHP workers.');
        $reserved = (float)$this->value('SELECT SUM(approved_amount) FROM claims WHERE policy_id = 1 AND status IN ("approved", "paid", "closed")');
        $this->db()->prepare('UPDATE policies SET coverage_limit = ? WHERE id = 1')->execute([$reserved + 5000000]);
        $this->db()->exec('UPDATE claims SET status = "submitted", adjuster_id = 2 WHERE id = 103');
        $clients = [$this->signIn(self::BRIAN), $this->signIn(self::BRIAN)];
        $responses = ApiClient::postJsonTogether($clients, 'config/api/claims-decision.php', [], [
            ['claim_id' => 101, 'decision' => 'approve', 'amount' => 5000000],
            ['claim_id' => 103, 'decision' => 'approve', 'amount' => 5000000],
        ]);
        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame(200, $statuses[0], json_encode($responses));
        $this->assertContains($statuses[1], [409, 422]);
        $this->assertSame(1, $this->rows('payouts', 'claim_id IN (101, 103)'));
    }

    public function testConcurrentEstimateDecisionsProduceOneAuditAndOneOutcome(): void
    {
        if (!TestServer::concurrent()) $this->markTestSkipped('Requires concurrent PHP workers.');
        $clients = [$this->signIn(self::BRIAN), $this->signIn(self::BRIAN), $this->signIn(self::BRIAN)];
        $responses = ApiClient::postJsonTogether($clients, 'config/api/estimates-decision.php', ['estimate_id' => 1, 'decision' => 'approve']);
        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame([200, 409, 409], $statuses, json_encode($responses));
        $this->assertSame(1, $this->rows('audit_log', 'entity_id = 1 AND action = "estimate.decision"'));
    }

    public function testFailedEstimateDecisionRollsBackAllChanges(): void
    {
        $this->db()->exec("CREATE TRIGGER fail_estimate_notification BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'");
        try {
            $response = $this->signIn(self::BRIAN)->postJson('config/api/estimates-decision.php', ['estimate_id' => 1, 'decision' => 'approve']);
            $this->assertStatus(500, $response);
            $this->assertSame('pending', $this->value('SELECT status FROM garage_estimates WHERE id = 1'));
            $this->assertSame('quoted', $this->value('SELECT status FROM work_orders WHERE claim_id = 101'));
            $this->assertSame(0, $this->rows('audit_log', 'action = "estimate.decision"'));
        } finally {
            $this->db()->exec('DROP TRIGGER fail_estimate_notification');
        }
    }

    public function testStartReviewAndClosureRespectAssignmentAndLifecycle(): void
    {
        $path = 'config/api/claims-status.php';
        $grace = $this->signIn(self::GRACE);
        $brian = $this->signIn(self::BRIAN);
        $this->assertStatus(403, $brian->postJson($path, ['claim_id' => 105, 'action' => 'start_review']));
        $this->assertStatus(200, $grace->postJson($path, ['claim_id' => 105, 'action' => 'start_review']));
        $this->assertSame('under_review', $this->claimStatus(105));
        $this->assertStatus(409, $grace->postJson($path, ['claim_id' => 105, 'action' => 'close']));
        $this->assertStatus(200, $brian->postJson($path, ['claim_id' => 106, 'action' => 'close']));
        $this->assertSame('closed', $this->claimStatus(106));
        $this->assertStatus(409, $brian->postJson($path, ['claim_id' => 106, 'action' => 'start_review']));
        $this->assertSame(1, $this->rows('claim_status_history', 'claim_id = 106 AND to_status = "closed"'));
    }

    public function testAdjusterCanIssuePolicyAndClaimantCanUseIt(): void
    {
        $fields = ['user_id' => 1, 'vehicle_make' => 'Toyota', 'vehicle_model' => 'Corolla', 'vehicle_plate' => 'UBZ 001A',
            'vehicle_year' => 2020, 'coverage_type' => 'comprehensive', 'coverage_limit' => 20000000, 'premium' => 500000,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31'];
        $brian = $this->signIn(self::BRIAN);
        $response = $brian->postJson('config/api/policies.php', $fields);
        $this->assertStatus(201, $response);
        $id = $response->json()['id'];
        $this->assertStatus(409, $brian->postJson('config/api/policies.php', $fields));
        $this->assertStatus(422, $brian->postJson('config/api/policies.php', array_replace($fields, ['end_date' => '2026-02-30'])));
        $doreen = $this->signIn(self::DOREEN);
        $this->assertStatus(403, $doreen->postJson('config/api/policies.php', $fields));
        $this->assertStatus(403, $doreen->get('config/api/policies.php'));
        $this->assertStatus(200, $doreen->postJson('config/api/claims.php', $this->claimForm(['policy_id' => $id])));
        $this->assertSame(1, $this->rows('audit_log', 'entity_id = ? AND action = "policy.created"', [$id]));
    }

    public function testPrivateFilesAreNotServedAndHealthReportsReadiness(): void
    {
        $client = $this->anonymous();
        foreach (['composer.json', '.env.example', '.git/config', 'config/db/seed-demo.php', 'config/api/_helpers.php', 'vendor/autoload.php', 'tests/bootstrap.php'] as $path) {
            $this->assertStatus(404, $client->get($path), $path);
        }
        $this->assertSame(['ok' => true], $client->get('config/api/health.php')->json());
    }

    public function testClosureEndsGarageWorkAndRefusesFurtherWrites(): void
    {
        $this->db()->exec('UPDATE claims SET status = "paid" WHERE id = 101');
        $brian = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $brian->postJson('config/api/claims-status.php', ['claim_id' => 101, 'action' => 'close']));
        $this->assertSame('closed', $this->value('SELECT status FROM work_orders WHERE claim_id = 101'));
        $this->assertStatus(409, $brian->postJson('config/api/claims-assign.php', ['claim_id' => 101, 'garage_id' => 7]));
        $this->assertStatus(409, $brian->postJson('config/api/estimates-decision.php', ['estimate_id' => 1, 'decision' => 'approve']));
        $this->assertStatus(409, $this->signIn(self::KIGONGO)->postJson('config/api/estimates.php',
            ['claim_id' => 101, 'parts_cost' => 1000, 'labor_cost' => 1000, 'repair_days' => 1]));
    }
}
