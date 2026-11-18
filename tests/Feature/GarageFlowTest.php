<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

/** Assigning a garage, quoting the repair, and the adjuster's answer to the quote. */
final class GarageFlowTest extends FeatureTestCase
{
    private const ASSIGN = 'config/api/claims-assign.php';
    private const QUOTE = 'config/api/estimates.php';
    private const ANSWER = 'config/api/estimates-decision.php';

    /** @return array<string,mixed> */
    private function quote(int $claimId, float $parts = 100000, float $labor = 50000, int $days = 5): array
    {
        return ['claim_id' => $claimId, 'parts_cost' => $parts, 'labor_cost' => $labor, 'other_cost' => 0, 'repair_days' => $days];
    }

    // ---- assigning a garage -------------------------------------------------

    public function testAnAdjusterAssignsAClaimToAnActiveGarage(): void
    {
        $response = $this->signIn(self::GRACE)->postJson(self::ASSIGN, ['claim_id' => 105, 'garage_id' => 7]);

        $this->assertStatus(200, $response);
        $this->assertSame(1, $this->rows('work_orders', "claim_id = 105 AND garage_user_id = 7 AND status = 'open' AND assigned_by = 8"));
        $this->assertSame(1, $this->rows('notifications', "user_id = 7 AND claim_id = 105 AND type = 'work_order'"));
        $this->assertSame(1, $this->rows('notifications', "user_id = 5 AND claim_id = 105 AND title = 'Garage assigned'"));
        $this->assertSame(1, $this->rows('audit_log', "action = 'claim.assign_garage' AND entity_id = 105"));
    }

    public function testAClaimAlreadyWithAGarageCannotBeSentToASecond(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::ASSIGN, ['claim_id' => 101, 'garage_id' => 7]);

        $this->assertStatus(409, $response);
        $this->assertSame(3, (int)$this->value('SELECT garage_user_id FROM work_orders WHERE claim_id = 101'));
    }

    public function testAClosedWorkOrderCanBeReopenedWithAnotherGarage(): void
    {
        $response = $this->signIn(self::GRACE)->postJson(self::ASSIGN, ['claim_id' => 107, 'garage_id' => 7]);

        $this->assertStatus(200, $response);
        $this->assertSame(1, $this->rows('work_orders', 'claim_id = 107'), 'the same work order is reused');
        $this->assertSame(1, $this->rows('work_orders', "claim_id = 107 AND garage_user_id = 7 AND status = 'open'"));
    }

    public function testOnlyActiveGaragesCanBeChosen(): void
    {
        $grace = $this->signIn(self::GRACE);

        $this->assertStatus(422, $grace->postJson(self::ASSIGN, ['claim_id' => 105, 'garage_id' => 5]), 'a claimant is not a garage');
        $this->assertStatus(422, $grace->postJson(self::ASSIGN, ['claim_id' => 105, 'garage_id' => 99999]), 'no such user');
        $this->assertSame(0, $this->rows('work_orders', 'claim_id = 105'));
    }

    public function testAnAdjusterCannotAssignAGarageOnAnotherDesk(): void
    {
        $this->assertStatus(403, $this->signIn(self::BRIAN)->postJson(self::ASSIGN, ['claim_id' => 105, 'garage_id' => 7]));
        $this->assertSame(0, $this->rows('work_orders', 'claim_id = 105'));
    }

    // ---- quoting ------------------------------------------------------------

    public function testAGarageQuotesAJobItHoldsAWorkOrderFor(): void
    {
        $this->signIn(self::GRACE)->postJson(self::ASSIGN, ['claim_id' => 105, 'garage_id' => 7]);

        $response = $this->signIn(self::SSEMWANGA)->postJson(self::QUOTE, $this->quote(105));

        $this->assertStatus(200, $response);
        $this->assertEquals(150000, $response->json()['total_estimate']);
        $this->assertSame(1, $this->rows('garage_estimates', "claim_id = 105 AND garage_user_id = 7 AND status = 'pending' AND total_estimate = 150000"));
        $this->assertSame('quoted', $this->value('SELECT status FROM work_orders WHERE claim_id = 105'));
        $this->assertSame(1, $this->rows('notifications', "user_id = 8 AND claim_id = 105 AND type = 'estimate'"), 'the adjuster is told');
        $this->assertSame(1, $this->rows('notifications', "user_id = 5 AND claim_id = 105 AND type = 'estimate'"), 'and so is the claimant');
    }

    public function testAGarageCannotQuoteAJobItWasNotGiven(): void
    {
        $response = $this->signIn(self::SSEMWANGA)->postJson(self::QUOTE, $this->quote(101));

        $this->assertStatus(403, $response);
        $this->assertSame(0, $this->rows('garage_estimates', 'garage_user_id = 7'));
    }

    public function testQuotesAreValidated(): void
    {
        $kigongo = $this->signIn(self::KIGONGO);

        $this->assertStatus(422, $kigongo->postJson(self::QUOTE, $this->quote(101, 0, 0)), 'a zero total');
        $this->assertStatus(422, $kigongo->postJson(self::QUOTE, $this->quote(101, days: 0)), 'no repair time');
        $this->assertStatus(422, $kigongo->postJson(self::QUOTE, $this->quote(101, days: 121)), 'an absurd repair time');
        $this->assertEquals(6850000, $this->value('SELECT total_estimate FROM garage_estimates WHERE id = 1'), 'the existing quote is untouched');
    }

    public function testAClosedWorkOrderCannotBeQuoted(): void
    {
        $this->assertStatus(409, $this->signIn(self::KIGONGO)->postJson(self::QUOTE, $this->quote(107)));
    }

    public function testRevisingASentBackQuoteReopensTheSameEstimate(): void
    {
        $response = $this->signIn(self::KIGONGO)->postJson(self::QUOTE, $this->quote(104, 6000000, 3000000, 10));

        $this->assertStatus(200, $response);
        $this->assertSame(1, $this->rows('garage_estimates', 'claim_id = 104'), 'revised in place, not duplicated');
        $this->assertSame('pending', $this->value('SELECT status FROM garage_estimates WHERE claim_id = 104'));
        $this->assertEquals(9000000, $this->value('SELECT total_estimate FROM garage_estimates WHERE claim_id = 104'));
        $this->assertNull($this->value('SELECT adjuster_notes FROM garage_estimates WHERE claim_id = 104'), 'the old feedback is cleared');
        $this->assertSame('quoted', $this->value('SELECT status FROM work_orders WHERE claim_id = 104'));
    }

    // ---- the adjuster's answer ---------------------------------------------

    public function testApprovingAQuoteClosesTheWorkOrderAndTellsEveryone(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::ANSWER, ['estimate_id' => 1, 'decision' => 'approve']);

        $this->assertStatus(200, $response);
        $this->assertSame('approved', $this->value('SELECT status FROM garage_estimates WHERE id = 1'));
        $this->assertSame('closed', $this->value('SELECT status FROM work_orders WHERE claim_id = 101'));
        $this->assertSame(1, $this->rows('notifications', "user_id = 3 AND claim_id = 101 AND title LIKE 'Estimate approved%'"));
        $this->assertSame(1, $this->rows('notifications', "user_id = 1 AND claim_id = 101 AND title = 'Estimate approved by adjuster'"));
    }

    public function testSendingAQuoteBackKeepsTheNotesAndReopensTheJob(): void
    {
        $response = $this->signIn(self::BRIAN)->postJson(self::ANSWER, ['estimate_id' => 1, 'decision' => 'send_back', 'notes' => 'Parts look high']);

        $this->assertStatus(200, $response);
        $this->assertSame('rejected', $this->value('SELECT status FROM garage_estimates WHERE id = 1'));
        $this->assertSame('Parts look high', $this->value('SELECT adjuster_notes FROM garage_estimates WHERE id = 1'));
        $this->assertSame('revision_requested', $this->value('SELECT status FROM work_orders WHERE claim_id = 101'));
        $this->assertSame(1, $this->rows('notifications', "user_id = 3 AND claim_id = 101 AND title = 'Revision requested'"));
    }

    public function testAQuoteCanOnlyBeActedOnOnce(): void
    {
        $brian = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $brian->postJson(self::ANSWER, ['estimate_id' => 1, 'decision' => 'approve']));

        $this->assertStatus(409, $brian->postJson(self::ANSWER, ['estimate_id' => 1, 'decision' => 'send_back']));
        $this->assertSame('approved', $this->value('SELECT status FROM garage_estimates WHERE id = 1'));
    }

    public function testAnAdjusterCannotActOnAQuoteFromAnotherDesk(): void
    {
        $this->assertStatus(403, $this->signIn(self::GRACE)->postJson(self::ANSWER, ['estimate_id' => 1, 'decision' => 'approve']));
        $this->assertSame('pending', $this->value('SELECT status FROM garage_estimates WHERE id = 1'));
    }

    public function testBadAnswersAreRefusedCleanly(): void
    {
        $brian = $this->signIn(self::BRIAN);

        $this->assertStatus(422, $brian->postJson(self::ANSWER, ['estimate_id' => 1, 'decision' => 'maybe']));
        $this->assertStatus(404, $brian->postJson(self::ANSWER, ['estimate_id' => 99999, 'decision' => 'approve']));
    }
}
