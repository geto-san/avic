<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

use Avic\Tests\Support\TestServer;

/** What an adjuster may decide, and when (claims-decision.php + claim-state.php). */
final class DecisionTest extends FeatureTestCase
{
    private const DECIDE = 'config/api/claims-decision.php';

    /** @return array<string,mixed> */
    private function approve(int $claimId, float $amount = 5000000): array
    {
        return ['claim_id' => $claimId, 'decision' => 'approve', 'amount' => $amount];
    }

    public function testApprovingAnOpenClaimOpensExactlyOnePayout(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::DECIDE, $this->approve(101));

        $this->assertStatus(200, $response);
        $this->assertSame('approved', $this->claimStatus(101));
        $this->assertEquals(5000000, $this->value('SELECT approved_amount FROM claims WHERE id = 101'));
        $this->assertSame(1, $this->rows('payouts', "claim_id = 101 AND status = 'pending' AND approved_by = 2 AND user_id = 1 AND amount = 5000000"));
        $this->assertSame(1, $this->rows('notifications', "user_id = 1 AND claim_id = 101 AND type = 'payout'"));
        $this->assertSame(1, $this->rows('claim_status_history', "claim_id = 101 AND from_status = 'under_review' AND to_status = 'approved'"));
        $this->assertSame(1, $this->rows('adjuster_reviews', "claim_id = 101 AND decision = 'approve'"));
        $this->assertSame(1, $this->rows('audit_log', "action = 'claim.decision' AND entity_id = 101"));
    }

    public function testApprovingTwiceIsRefusedAndNeverCreatesASecondPayout(): void
    {
        $brian = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $brian->postJson(self::DECIDE, $this->approve(101)));

        $this->assertStatus(409, $brian->postJson(self::DECIDE, $this->approve(101)));
        $this->assertSame(1, $this->rows('payouts', 'claim_id = 101'));
    }

    /** @dataProvider alreadyDecided */
    public function testADecidedClaimTakesNoFurtherDecision(int $claimId, string $status, string $decision): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::DECIDE, ['claim_id' => $claimId, 'decision' => $decision, 'amount' => 1000000, 'review_notes' => 'again']);

        $this->assertStatus(409, $response);
        $this->assertSame($status, $this->claimStatus($claimId));
    }

    /** @return array<string,array{int,string,string}> */
    public static function alreadyDecided(): array
    {
        return [
            'approve an approved claim' => [108, 'approved', 'approve'],
            'reject an approved claim'  => [108, 'approved', 'reject'],
            'approve a paid claim'      => [102, 'paid', 'approve'],
            'approve a rejected claim'  => [106, 'rejected', 'approve'],
            'reject a rejected claim'   => [106, 'rejected', 'reject'],
        ];
    }

    public function testApprovingAnApprovedClaimCreatesNoPayout(): void
    {
        $this->signIn(self::BRIAN)->postJson(self::DECIDE, $this->approve(108));

        $this->assertSame(0, $this->rows('payouts', 'claim_id = 108'));
    }

    public function testRejectionRecordsTheReasonAndOpensNoPayout(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::DECIDE, ['claim_id' => 101, 'decision' => 'reject', 'review_notes' => 'Not covered']);

        $this->assertStatus(200, $response);
        $this->assertSame('rejected', $this->claimStatus(101));
        $this->assertSame('Not covered', $this->value('SELECT rejection_reason FROM claims WHERE id = 101'));
        $this->assertNotNull($this->value('SELECT resolved_at FROM claims WHERE id = 101'));
        $this->assertSame(0, $this->rows('payouts', 'claim_id = 101'));
        $this->assertSame(1, $this->rows('notifications', "user_id = 1 AND claim_id = 101 AND title = 'Claim rejected'"));
    }

    public function testAskingForDocumentsParksTheClaimOnTheClaimant(): void
    {
        $brian = $this->signIn(self::BRIAN);

        $this->assertStatus(200, $brian->postJson(self::DECIDE, ['claim_id' => 101, 'decision' => 'request_docs', 'review_notes' => 'Need the police report']));
        $this->assertSame('pending_docs', $this->claimStatus(101));
        $this->assertSame(1, $this->rows('notifications', "user_id = 1 AND claim_id = 101 AND title = 'More documents needed'"));

        $this->assertStatus(409, $brian->postJson(self::DECIDE, ['claim_id' => 101, 'decision' => 'request_docs']), 'asking again');
    }

    public function testAClaimWaitingForDocumentsCanStillBeRejected(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::DECIDE, ['claim_id' => 104, 'decision' => 'reject', 'review_notes' => 'No documents came']);

        $this->assertStatus(200, $response);
        $this->assertSame('rejected', $this->claimStatus(104));
    }

    public function testApprovalNeedsAPositiveAmount(): void
    {
        $brian = $this->signIn(self::BRIAN);

        $this->assertStatus(422, $brian->postJson(self::DECIDE, ['claim_id' => 101, 'decision' => 'approve']), 'no amount');
        $this->assertStatus(422, $brian->postJson(self::DECIDE, $this->approve(101, 0)), 'zero');
        $this->assertStatus(422, $brian->postJson(self::DECIDE, $this->approve(101, -50)), 'negative');
        $this->assertSame('under_review', $this->claimStatus(101));
    }

    public function testApprovalCannotExceedTheCoverLimit(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::DECIDE, $this->approve(101, 999999999));

        $this->assertStatus(422, $response);
        $this->assertStringContainsString('cover limit', $response->message());
        $this->assertSame('under_review', $this->claimStatus(101));
    }

    public function testApprovalIsRefusedWhenTheIncidentIsOutsideThePolicyPeriod(): void
    {
        $this->db()->exec("UPDATE claims SET incident_date = '2025-12-31' WHERE id = 101");

        $response = $this->signIn(self::BRIAN)->postJson(self::DECIDE, $this->approve(101));

        $this->assertStatus(422, $response);
        $this->assertStringContainsString('policy period', $response->message());
    }

    public function testTheBasicTierDoesNotCoverTheft(): void
    {
        $this->db()->exec("UPDATE claims SET policy_id = 4, claim_type = 'theft' WHERE id = 105");

        $response = $this->signIn(self::GRACE)->postJson(self::DECIDE, $this->approve(105, 1000000));

        $this->assertStatus(422, $response);
        $this->assertStringContainsString('basic', $response->message());
    }

    public function testAnAdjusterCannotDecideAClaimOnAnotherDesk(): void
    {
        $response = $this->signIn(self::GRACE)->postJson(self::DECIDE, $this->approve(101));

        $this->assertStatus(403, $response);
        $this->assertSame('under_review', $this->claimStatus(101));
    }

    public function testAnUnknownClaimIsNotFound(): void
    {
        $this->assertStatus(404, $this->signIn(self::BRIAN)->postJson(self::DECIDE, $this->approve(99999)));
    }

    public function testOnlyAdjustersMayDecide(): void
    {
        $body = $this->approve(101);
        $this->assertStatus(401, $this->anonymous()->postJson(self::DECIDE, $body));
        $this->assertStatus(403, $this->signIn(self::DOREEN)->postJson(self::DECIDE, $body));
        $this->assertStatus(403, $this->signIn(self::KIGONGO)->postJson(self::DECIDE, $body));
        $this->assertSame('under_review', $this->claimStatus(101));
    }

    public function testAnUnknownDecisionIsRejected(): void
    {
        $this->assertStatus(422, $this->signIn(self::BRIAN)->postJson(self::DECIDE, ['claim_id' => 101, 'decision' => 'maybe']));
    }

    public function testAFailedDecisionLeavesNothingBehind(): void
    {
        $tables = ['claim_status_history', 'adjuster_reviews', 'audit_log', 'notifications', 'payouts'];
        $before = array_map(fn(string $t) => $this->rows($t), $tables);

        $this->assertStatus(422, $this->signIn(self::BRIAN)->postJson(self::DECIDE, $this->approve(101, 999999999)));

        $this->assertSame($before, array_map(fn(string $t) => $this->rows($t), $tables), 'a refused decision must not write anywhere');
    }

    public function testSimultaneousApprovalsFromSeparateSessionsProduceExactlyOnePayout(): void
    {
        if (!TestServer::concurrent()) {
            $this->markTestSkipped('needs setsid to run the test server with several workers');
        }
        // the same adjuster on three devices: three sessions, so the requests truly overlap
        $devices = [$this->signIn(self::GRACE), $this->signIn(self::GRACE), $this->signIn(self::GRACE)];

        for ($round = 1; $round <= 8; $round++) {
            $this->db()->exec("UPDATE claims SET status = 'submitted', approved_amount = NULL WHERE id = 105");
            $this->db()->exec('DELETE FROM payouts WHERE claim_id = 105');

            $codes = array_map(
                static fn($r) => $r->status,
                \Avic\Tests\Support\ApiClient::postJsonTogether($devices, self::DECIDE, $this->approve(105, 2000000))
            );
            sort($codes);

            $this->assertSame([200, 409, 409], $codes, "round $round");
            $this->assertSame(1, $this->rows('payouts', 'claim_id = 105'), "round $round: payouts");
        }
    }
}
