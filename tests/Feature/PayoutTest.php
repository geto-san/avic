<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

/** The payout lifecycle (payouts-update.php). */
final class PayoutTest extends FeatureTestCase
{
    private const PAYOUT = 'config/api/payouts-update.php';

    /** Approve claim 101 as Brian and return the new payout's id. */
    private function approvedPayout(\Avic\Tests\Support\ApiClient $brian): int
    {
        $this->assertStatus(200, $brian->postJson('config/api/claims-decision.php', ['claim_id' => 101, 'decision' => 'approve', 'amount' => 5000000]));
        return (int)$this->value('SELECT id FROM payouts WHERE claim_id = 101');
    }

    public function testAPayoutWalksFromPendingToCompletedAndPaysTheClaim(): void
    {
        $brian = $this->signIn(self::BRIAN);
        $payout = $this->approvedPayout($brian);

        $this->assertStatus(200, $brian->postJson(self::PAYOUT, ['payout_id' => $payout, 'action' => 'mark_processing']));
        $this->assertSame('processing', $this->value('SELECT status FROM payouts WHERE id = ?', [$payout]));
        $this->assertSame('approved', $this->claimStatus(101), 'the claim is not paid until the payout completes');

        $this->assertStatus(200, $brian->postJson(self::PAYOUT, ['payout_id' => $payout, 'action' => 'mark_paid']));
        $this->assertSame('completed', $this->value('SELECT status FROM payouts WHERE id = ?', [$payout]));
        $this->assertSame('paid', $this->claimStatus(101));
        $this->assertSame(2, $this->rows('notifications', "user_id = 1 AND claim_id = 101 AND type = 'payout' AND title LIKE 'Payout%'"));
        $this->assertSame(2, $this->rows('audit_log', "entity_type = 'payout' AND entity_id = ?", [$payout]));
    }

    public function testStepsCannotBeSkippedOrRepeated(): void
    {
        $brian = $this->signIn(self::BRIAN);
        $payout = $this->approvedPayout($brian);
        $step = static fn(string $action) => ['payout_id' => $payout, 'action' => $action];

        $this->assertStatus(409, $brian->postJson(self::PAYOUT, $step('mark_paid')), 'pending -> completed skips a step');
        $this->assertStatus(200, $brian->postJson(self::PAYOUT, $step('mark_processing')));
        $this->assertStatus(409, $brian->postJson(self::PAYOUT, $step('mark_processing')), 'processing twice');
        $this->assertStatus(200, $brian->postJson(self::PAYOUT, $step('mark_paid')));
        $this->assertStatus(409, $brian->postJson(self::PAYOUT, $step('mark_paid')), 'completing twice');
        $this->assertStatus(409, $brian->postJson(self::PAYOUT, $step('mark_processing')), 'going backwards');
    }

    public function testASeededProcessingPayoutCanBeCompleted(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::PAYOUT, ['payout_id' => 2, 'action' => 'mark_paid']);

        $this->assertStatus(200, $response);
        $this->assertSame('paid', $this->claimStatus(107));
    }

    public function testOnlyTheApprovingAdjusterMayAdvanceAPayout(): void
    {
        $response = $this->signIn(self::GRACE)->postJson(self::PAYOUT, ['payout_id' => 2, 'action' => 'mark_paid']);

        $this->assertStatus(403, $response);
        $this->assertSame('processing', $this->value('SELECT status FROM payouts WHERE id = 2'));
    }

    public function testAPayoutCannotMoveOnceItsClaimIsNoLongerApproved(): void
    {
        $this->db()->exec("UPDATE claims SET status = 'rejected' WHERE id = 107");

        $response = $this->signIn(self::BRIAN)->postJson(self::PAYOUT, ['payout_id' => 2, 'action' => 'mark_paid']);

        $this->assertStatus(409, $response);
        $this->assertSame('processing', $this->value('SELECT status FROM payouts WHERE id = 2'));
    }

    public function testBadRequestsAreRefusedCleanly(): void
    {
        $brian = $this->signIn(self::BRIAN);

        $this->assertStatus(404, $brian->postJson(self::PAYOUT, ['payout_id' => 99999, 'action' => 'mark_paid']), 'unknown payout');
        $this->assertStatus(422, $brian->postJson(self::PAYOUT, ['payout_id' => 2, 'action' => 'refund']), 'unknown action');
        $this->assertStatus(422, $brian->postJson(self::PAYOUT, ['action' => 'mark_paid']), 'no payout id');
    }

    public function testOnlyAdjustersMayAdvancePayouts(): void
    {
        $body = ['payout_id' => 2, 'action' => 'mark_paid'];
        $this->assertStatus(401, $this->anonymous()->postJson(self::PAYOUT, $body));
        $this->assertStatus(403, $this->signIn(self::ALEX)->postJson(self::PAYOUT, $body), 'the claimant being paid cannot mark it paid');
        $this->assertStatus(403, $this->signIn(self::KIGONGO)->postJson(self::PAYOUT, $body));
        $this->assertSame('processing', $this->value('SELECT status FROM payouts WHERE id = 2'));
    }

    public function testSimultaneousAdvancesFromSeparateSessionsMoveThePayoutOnlyOnce(): void
    {
        if (!\Avic\Tests\Support\TestServer::concurrent()) {
            $this->markTestSkipped('needs setsid to run the test server with several workers');
        }
        $devices = [$this->signIn(self::BRIAN), $this->signIn(self::BRIAN), $this->signIn(self::BRIAN)];

        for ($round = 1; $round <= 8; $round++) {
            $this->db()->exec("UPDATE payouts SET status = 'processing' WHERE id = 2");
            $this->db()->exec("UPDATE claims SET status = 'approved' WHERE id = 107");
            $before = $this->rows('audit_log', "action = 'payout.completed'");

            $codes = array_map(
                static fn($r) => $r->status,
                \Avic\Tests\Support\ApiClient::postJsonTogether($devices, self::PAYOUT, ['payout_id' => 2, 'action' => 'mark_paid'])
            );
            sort($codes);

            $this->assertSame([200, 409, 409], $codes, "round $round");
            $this->assertSame($before + 1, $this->rows('audit_log', "action = 'payout.completed'"), "round $round: completed exactly once");
        }
    }
}
