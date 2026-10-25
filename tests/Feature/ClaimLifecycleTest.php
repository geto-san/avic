<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

/** What a claimant may do to a claim, and when (claims.php + claim-state.php). */
final class ClaimLifecycleTest extends FeatureTestCase
{
    private const SUBMIT = 'config/api/claims.php';

    public function testAPaidClaimCannotBeResubmitted(): void
    {
        $doreen = $this->signIn(self::DOREEN);
        $response = $doreen->postJson(self::SUBMIT, $this->claimForm(['id' => 102, 'incident_date' => '2026-07-19']));

        $this->assertStatus(409, $response);
        $this->assertStringContainsString('paid', $response->message());
        $this->assertSame('paid', $this->claimStatus(102));
    }

    public function testAnApprovedClaimCannotBePushedBackToADraft(): void
    {
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm(['id' => 108, 'mode' => 'draft', 'incident_date' => '2026-08-22']));

        $this->assertStatus(409, $response);
        $this->assertSame('approved', $this->claimStatus(108));
    }

    public function testAClaimUnderReviewCannotBeEditedByTheClaimant(): void
    {
        $original = $this->value('SELECT incident_description FROM claims WHERE id = 101');
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm(['id' => 101, 'incident_description' => 'Changed my story']));

        $this->assertStatus(409, $response);
        $this->assertSame($original, $this->value('SELECT incident_description FROM claims WHERE id = 101'));
    }

    public function testASubmittedClaimCannotBeSubmittedAgain(): void
    {
        $doreen = $this->signIn(self::DOREEN);
        $this->assertStatus(200, $doreen->postJson(self::SUBMIT, $this->claimForm(['id' => 103])));

        $this->assertStatus(409, $doreen->postJson(self::SUBMIT, $this->claimForm(['id' => 103])));
    }

    public function testADraftCanBeSavedAndStaysADraft(): void
    {
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm(['id' => 103, 'mode' => 'draft', 'incident_location' => 'Jinja Road']));

        $this->assertStatus(200, $response);
        $this->assertSame('draft', $this->claimStatus(103));
        $this->assertSame('Jinja Road', $this->value('SELECT incident_location FROM claims WHERE id = 103'));
        $this->assertNull($this->value('SELECT adjuster_id FROM claims WHERE id = 103'), 'a draft is nobody\'s work yet');
    }

    public function testSubmittingASavedDraftRoutesItToAnAdjuster(): void
    {
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm(['id' => 103]));

        $this->assertStatus(200, $response);
        $this->assertSame('submitted', $this->claimStatus(103));
        $adjuster = (int)$this->value('SELECT adjuster_id FROM claims WHERE id = 103');
        $this->assertContains($adjuster, [2, 8], 'the claim must land in an adjuster\'s queue, not nowhere');
        $this->assertNotFalse($this->value('SELECT submitted_at FROM claims WHERE id = 103'));
        $this->assertSame(1, $this->rows('notifications', "user_id = ? AND claim_id = 103 AND type = 'claim_assigned'", [$adjuster]));
    }

    public function testSubmittingRecordsHistoryAndAnAuditEntry(): void
    {
        $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm(['id' => 103]));

        $this->assertSame(1, $this->rows('claim_status_history', "claim_id = 103 AND from_status = 'draft' AND to_status = 'submitted'"));
        $this->assertSame(1, $this->rows('audit_log', "action = 'claim.submitted' AND entity_id = 103"));
    }

    public function testAClaimWaitingForDocumentsCanBeAnsweredButNotMovedBackToADraft(): void
    {
        $alex = $this->signIn(self::ALEX);

        $draft = $alex->postJson(self::SUBMIT, $this->claimForm(['id' => 104, 'mode' => 'draft', 'policy_id' => 3]));
        $this->assertStatus(409, $draft);
        $this->assertSame('pending_docs', $this->claimStatus(104));

        $answer = $alex->postJson(self::SUBMIT, $this->claimForm(['id' => 104, 'policy_id' => 3]));
        $this->assertStatus(200, $answer);
        $this->assertSame('submitted', $this->claimStatus(104));
        $this->assertSame(2, (int)$this->value('SELECT adjuster_id FROM claims WHERE id = 104'), 'it goes back to the same adjuster');
    }

    public function testANewClaimIsNumberedAndRouted(): void
    {
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm());

        $this->assertStatus(200, $response);
        $id = (int)$response->json()['id'];
        $this->assertMatchesRegularExpression('/^CLM-\d{4}-\d{5}$/', (string)$response->json()['claim_number']);
        $this->assertSame('submitted', $this->claimStatus($id));
        $this->assertNotNull($this->value('SELECT adjuster_id FROM claims WHERE id = ?', [$id]));
    }

    public function testANewDraftHasNoAdjusterAndNoSubmissionTime(): void
    {
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, ['mode' => 'draft', 'incident_description' => 'half done']);

        $this->assertStatus(200, $response);
        $id = (int)$response->json()['id'];
        $this->assertSame('draft', $this->claimStatus($id));
        $this->assertNull($this->value('SELECT adjuster_id FROM claims WHERE id = ?', [$id]));
        $this->assertNull($this->value('SELECT submitted_at FROM claims WHERE id = ?', [$id]));
    }

    public function testAClaimantCannotTouchSomeoneElsesClaim(): void
    {
        $response = $this->signIn(self::ALEX)->postJson(self::SUBMIT, $this->claimForm(['id' => 103, 'policy_id' => 3]));

        $this->assertContains($response->status, [403, 404]);
        $this->assertSame('draft', $this->claimStatus(103));
    }

    public function testSubmittingNeedsEveryRequiredField(): void
    {
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm(['incident_location' => '']));

        $this->assertStatus(422, $response);
        $this->assertSame(0, $this->rows('claims', "incident_description = 'Hit while parked.'"));
    }

    public function testAClaimMustBeOnTheClaimantsOwnActivePolicy(): void
    {
        $doreen = $this->signIn(self::DOREEN);

        $this->assertStatus(422, $doreen->postJson(self::SUBMIT, $this->claimForm(['policy_id' => 3])), "someone else's policy");
        $this->assertStatus(422, $doreen->postJson(self::SUBMIT, $this->claimForm(['policy_id' => 2])), 'her own but expired policy');
    }

    public function testASubmittedClaimNeedsAKnownClaimType(): void
    {
        $response = $this->signIn(self::DOREEN)->postJson(self::SUBMIT, $this->claimForm(['claim_type' => 'bogus']));

        $this->assertStatus(422, $response, 'an unknown type must be a clean 422, not a database error');
    }

    public function testASubmittedClaimNeedsARealIncidentDate(): void
    {
        $doreen = $this->signIn(self::DOREEN);

        $this->assertStatus(422, $doreen->postJson(self::SUBMIT, $this->claimForm(['incident_date' => 'not-a-date'])), 'garbage date');
        $this->assertStatus(422, $doreen->postJson(self::SUBMIT, $this->claimForm(['incident_date' => '2999-01-01'])), 'a date in the future');
    }

    public function testOnlyClaimantsMayUseThisEndpoint(): void
    {
        $this->assertStatus(401, $this->anonymous()->postJson(self::SUBMIT, $this->claimForm()));
        $this->assertStatus(403, $this->signIn(self::BRIAN)->postJson(self::SUBMIT, $this->claimForm()));
        $this->assertStatus(403, $this->signIn(self::KIGONGO)->postJson(self::SUBMIT, $this->claimForm()));
    }

    public function testOnlyPostIsAccepted(): void
    {
        $this->assertStatus(405, $this->signIn(self::DOREEN)->get(self::SUBMIT));
    }
}
