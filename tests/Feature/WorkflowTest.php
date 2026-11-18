<?php
declare(strict_types=1);
namespace Avic\Tests\Feature;

/** New business controls exercised through the same HTTP endpoints as the UI. */
final class WorkflowTest extends FeatureTestCase
{
    private function approve(): int
    {
        $this->assertStatus(200, $this->signIn(self::BRIAN)->postJson('config/api/claims-decision.php', ['claim_id'=>101,'decision'=>'approve','amount'=>5000000]));
        return (int)$this->value('SELECT id FROM payouts WHERE claim_id = 101');
    }

    private function acceptance(): array
    {
        return ['claim_id'=>101,'action'=>'accept','confirmed'=>true,'payment_method'=>'mobile_money','account_name'=>'Doreen','account_number'=>'+256700123456'];
    }

    private function evidence(int $payout): array
    {
        return ['payout_id'=>$payout,'action'=>'mark_paid','external_reference'=>'PROVIDER-RECEIPT-001','verification_note'=>'Checked the provider receipt and paid amount against this payout.','payment_confirmed'=>true];
    }

    public function testReasonsAreRequiredAndDocumentRequestsReachTheOwner(): void
    {
        $desk = $this->signIn(self::BRIAN);
        foreach (['reject','request_docs'] as $decision) {
            $this->assertStatus(422, $desk->postJson('config/api/claims-decision.php', ['claim_id'=>101,'decision'=>$decision]));
        }
        $details = "Police report\nPhoto of the rear bumper";
        $this->assertStatus(200, $desk->postJson('config/api/claims-decision.php', ['claim_id'=>101,'decision'=>'request_docs','review_notes'=>$details]));
        $data = $this->signIn(self::DOREEN)->get('config/api/bootstrap.php')->json();
        $this->assertSame($details, $data['documentRequests'][0]['details']);
        $this->assertStringContainsString($details, $data['notifications'][0]['message']);
        $garage = $this->signIn(self::KIGONGO)->get('config/api/bootstrap.php')->json();
        $this->assertSame([], $garage['documentRequests']);
        $this->assertSame([], $garage['settlements']);
        $this->assertSame([], $garage['paymentEvidence']);
    }

    public function testOwnerMustAcceptBeforeProcessingAndAcceptanceCannotRepeat(): void
    {
        $payout = $this->approve();
        $desk = $this->signIn(self::BRIAN);
        $this->assertStatus(409, $desk->postJson('config/api/payouts-update.php', ['payout_id'=>$payout,'action'=>'mark_processing']));
        $this->assertStatus(403, $this->signIn(self::ALEX)->postJson('config/api/settlements.php', $this->acceptance()));
        $owner = $this->signIn(self::DOREEN);
        $this->assertStatus(422, $owner->postJson('config/api/settlements.php', array_replace($this->acceptance(), ['confirmed'=>false])));
        $this->assertStatus(422, $owner->postJson('config/api/settlements.php', array_replace($this->acceptance(), ['payment_method'=>'bank_transfer'])));
        $this->assertStatus(200, $owner->postJson('config/api/settlements.php', $this->acceptance()));
        $this->assertStatus(409, $owner->postJson('config/api/settlements.php', $this->acceptance()));
        $this->assertSame('+256700123456', $this->value('SELECT account_number FROM payouts WHERE id = ?', [$payout]));
        $this->assertStatus(200, $desk->postJson('config/api/payouts-update.php', ['payout_id'=>$payout,'action'=>'mark_processing']));
    }

    public function testRequestedDocumentsNeedANewUploadAndKeepTheOriginalDeadline(): void
    {
        $desk = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $desk->postJson('config/api/claims-decision.php', ['claim_id'=>101,'decision'=>'request_docs','review_notes'=>'Clear accident photograph']));
        $owner = $this->signIn(self::DOREEN);
        $submitted = $this->value('SELECT submitted_at FROM claims WHERE id = 101');
        $claim = $this->db()->query('SELECT * FROM claims WHERE id = 101')->fetch();
        $form = $this->claimForm(['id'=>101,'policy_id'=>$claim['policy_id'],'incident_date'=>$claim['incident_date'],'incident_location'=>$claim['incident_location']]);
        $this->assertStatus(409, $owner->postMultipart('config/api/claims.php', $form));
        $this->assertSame('pending_docs', $this->claimStatus(101));
        $png = $this->pngFile();
        try {
            $item = (int)$this->value('SELECT MAX(id) FROM requested_documents');
            $this->assertStatus(200, $owner->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$item], [['path'=>$png,'type'=>'image/png','name'=>'requested-photo.png']]));
        } finally { unlink($png); }
        $this->assertSame('submitted', $this->claimStatus(101));
        $this->assertSame('answered', $this->value('SELECT status FROM claim_document_requests WHERE claim_id = 101'));
        $this->assertSame($submitted, $this->value('SELECT submitted_at FROM claims WHERE id = 101'));
        $documentId = (int)$this->value('SELECT MAX(id) FROM claim_documents');
        $reviewer = $this->signIn(self::GRACE);
        $this->assertStatus(403, $reviewer->get('config/uploads.php?doc=' . $documentId));
        $this->assertStatus(200, $owner->postJson('config/api/complaints.php', ['claim_id'=>101,'details'=>'Please review the evidence handling.']));
        $this->assertStatus(200, $reviewer->get('config/uploads.php?doc=' . $documentId));
        $this->assertStatus(403, $reviewer->postJson('config/api/claims-decision.php', ['claim_id'=>101,'decision'=>'reject','review_notes'=>'Cannot alter another desk claim.']));
    }

    public function testCompletedPaymentRequiresEvidenceAndConfirmationAtomically(): void
    {
        $payout = $this->approve();
        $this->assertStatus(200, $this->signIn(self::DOREEN)->postJson('config/api/settlements.php', $this->acceptance()));
        $desk = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $desk->postJson('config/api/payouts-update.php', ['payout_id'=>$payout,'action'=>'mark_processing']));
        foreach ([['external_reference'=>''],['verification_note'=>''],['payment_confirmed'=>false]] as $bad) {
            $this->assertStatus(422, $desk->postJson('config/api/payouts-update.php', array_replace($this->evidence($payout), $bad)));
        }
        $this->assertSame('approved', $this->claimStatus(101));
        $this->assertSame(0, $this->rows('payout_evidence'));
        $this->assertStatus(200, $desk->postJson('config/api/payouts-update.php', $this->evidence($payout)));
        $this->assertSame('paid', $this->claimStatus(101));
        $this->assertSame(1, $this->rows('payout_evidence'));
        $this->assertStatus(409, $desk->postJson('config/api/payouts-update.php', $this->evidence($payout)));
    }

    public function testDisputeHasAnIndependentReviewerAndPreservesTheDecision(): void
    {
        $payout = $this->approve();
        $owner = $this->signIn(self::DOREEN);
        $this->assertStatus(200, $owner->postJson('config/api/settlements.php', ['claim_id'=>101,'action'=>'dispute','details'=>'Please explain the difference from the repair quote.']));
        $complaint = (int)$this->value('SELECT id FROM claim_complaints WHERE claim_id = 101');
        $this->assertSame(8, (int)$this->value('SELECT reviewer_id FROM claim_complaints WHERE id = ?', [$complaint]));
        $this->assertStatus(409, $owner->postJson('config/api/settlements.php', $this->acceptance()));
        $this->assertStatus(403, $this->signIn(self::BRIAN)->postJson('config/api/complaints.php', ['id'=>$complaint,'action'=>'start_review']));
        $reviewer = $this->signIn(self::GRACE);
        $this->assertStatus(409, $reviewer->postJson('config/api/complaints.php', ['id'=>$complaint,'action'=>'resolve','response'=>'Reviewed.']));
        $this->assertStatus(200, $reviewer->postJson('config/api/complaints.php', ['id'=>$complaint,'action'=>'start_review']));
        $this->assertStatus(422, $reviewer->postJson('config/api/complaints.php', ['id'=>$complaint,'action'=>'resolve']));
        $this->assertStatus(200, $reviewer->postJson('config/api/complaints.php', ['id'=>$complaint,'action'=>'resolve','response'=>'The quote includes items outside this approved repair. The offer is unchanged.']));
        $this->assertSame('approved', $this->claimStatus(101));
        $this->assertSame(5000000.0, (float)$this->value('SELECT approved_amount FROM claims WHERE id = 101'));
        $this->assertStatus(200, $owner->postJson('config/api/settlements.php', $this->acceptance()));
        $this->assertStatus(200, $this->signIn(self::BRIAN)->postJson('config/api/payouts-update.php', ['payout_id'=>$payout,'action'=>'mark_processing']));
    }

    public function testComplaintsArePrivateAndBlockClosure(): void
    {
        $owner = $this->signIn(self::DOREEN);
        $body = ['claim_id'=>102,'details'=>'I need clarification of the final payment.'];
        $this->assertStatus(200, $owner->postJson('config/api/complaints.php', $body));
        $this->assertStatus(409, $owner->postJson('config/api/complaints.php', $body));
        $this->assertStatus(403, $this->signIn(self::ALEX)->postJson('config/api/complaints.php', $body));
        $this->assertStatus(403, $this->signIn(self::KIGONGO)->get('config/api/complaints.php'));
        $this->assertSame([], $this->signIn(self::ALEX)->get('config/api/complaints.php')->json()['complaints']);
        $this->assertSame([], $this->signIn(self::BRIAN)->get('config/api/complaints.php')->json()['complaints']);
        $this->assertCount(1, $this->signIn(self::GRACE)->get('config/api/complaints.php')->json()['complaints']);
        $this->assertStatus(409, $this->signIn(self::BRIAN)->postJson('config/api/claims-status.php', ['claim_id'=>102,'action'=>'close']));
        $this->assertSame('paid', $this->claimStatus(102));
    }

    public function testExternalReferenceCannotBeReusedAcrossPayouts(): void
    {
        $desk = $this->signIn(self::BRIAN);
        $this->assertStatus(200, $desk->postJson('config/api/payouts-update.php', $this->evidence(2)));
        $payout = $this->approve();
        $this->assertStatus(200, $this->signIn(self::DOREEN)->postJson('config/api/settlements.php', $this->acceptance()));
        $this->assertStatus(200, $desk->postJson('config/api/payouts-update.php', ['payout_id'=>$payout,'action'=>'mark_processing']));
        $this->assertStatus(409, $desk->postJson('config/api/payouts-update.php', $this->evidence($payout)));
        $this->assertSame('approved', $this->claimStatus(101));
    }

    public function testAcceptanceRollsBackWhenItsNotificationFails(): void
    {
        $this->approve();
        $owner = $this->signIn(self::DOREEN);
        $this->db()->exec("CREATE TRIGGER fail_settlement_notice BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test failure'");
        try {
            $this->assertStatus(500, $owner->postJson('config/api/settlements.php', $this->acceptance()));
            $this->assertSame('pending', $this->value('SELECT status FROM claim_settlements WHERE claim_id = 101'));
            $this->assertSame(0, $this->rows('audit_log', "action = 'settlement.accepted'"));
        } finally {
            $this->db()->exec('DROP TRIGGER fail_settlement_notice');
        }
    }

    public function testConcurrentAcceptancesRecordConsentOnlyOnce(): void
    {
        if (!\Avic\Tests\Support\TestServer::concurrent()) $this->markTestSkipped('Needs concurrent test workers.');
        $this->approve();
        $clients = [$this->signIn(self::DOREEN), $this->signIn(self::DOREEN), $this->signIn(self::DOREEN)];
        $codes = array_map(static fn($response) => $response->status,
            \Avic\Tests\Support\ApiClient::postJsonTogether($clients, 'config/api/settlements.php', $this->acceptance()));
        sort($codes);
        $this->assertSame([200,409,409], $codes);
        $this->assertSame(1, $this->rows('audit_log', "action = 'settlement.accepted'"));
    }

    public function testLegacyRecoveryCreatesPendingRecordsWithoutInventingConsent(): void
    {
        $this->db()->exec('DELETE FROM claim_settlements WHERE claim_id = 108');
        $this->db()->exec('DELETE FROM payouts WHERE claim_id = 108');
        $this->db()->exec('DELETE FROM schema_migrations WHERE version = "0004_recover_pending_offers"');
        $this->assertSame(['0004_recover_pending_offers'], \migrate($this->db()));
        $this->assertSame('pending', $this->value('SELECT status FROM payouts WHERE claim_id = 108'));
        $this->assertSame('pending', $this->value('SELECT status FROM claim_settlements WHERE claim_id = 108'));
        $this->assertNull($this->value('SELECT accepted_at FROM claim_settlements WHERE claim_id = 108'));
        $this->assertSame('completed', $this->value('SELECT status FROM payouts WHERE claim_id = 102'));
        $this->assertSame([], \migrate($this->db()));
    }
}
