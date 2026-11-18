<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

/** The inbox (notifications.php) and document verification (documents.php). */
final class NotificationsDocumentsTest extends FeatureTestCase
{
    private const NOTIFY = 'config/api/notifications.php';
    private const DOCS = 'config/api/documents.php';

    private function unread(int $userId): int
    {
        return $this->rows('notifications', 'user_id = ? AND is_read = 0', [$userId]);
    }

    public function testAUserCanMarkOneOfTheirNotificationsRead(): void
    {
        $id = (int)$this->value('SELECT id FROM notifications WHERE user_id = 1 AND is_read = 0 ORDER BY id LIMIT 1');

        $this->assertStatus(200, $this->signIn(self::DOREEN)->postJson(self::NOTIFY, ['action' => 'read', 'id' => $id]));

        $this->assertSame(1, $this->rows('notifications', 'id = ? AND is_read = 1', [$id]));
        $this->assertSame(1, $this->unread(1), 'only that one changed');
    }

    public function testNobodyCanReadAnotherUsersNotification(): void
    {
        $id = (int)$this->value('SELECT id FROM notifications WHERE user_id = 1 AND is_read = 0 ORDER BY id LIMIT 1');

        $this->assertStatus(404, $this->signIn(self::ALEX)->postJson(self::NOTIFY, ['action' => 'read', 'id' => $id]));
        $this->assertSame(2, $this->unread(1));
    }

    public function testReadAllOnlyClearsTheCallersInbox(): void
    {
        $adjusterUnread = $this->unread(2);

        $this->assertStatus(200, $this->signIn(self::DOREEN)->postJson(self::NOTIFY, ['action' => 'read_all']));

        $this->assertSame(0, $this->unread(1));
        $this->assertSame($adjusterUnread, $this->unread(2), "someone else's inbox is untouched");
    }

    public function testNotificationsNeedASessionAndAKnownAction(): void
    {
        $this->assertStatus(401, $this->anonymous()->postJson(self::NOTIFY, ['action' => 'read_all']));
        $this->assertStatus(422, $this->signIn(self::DOREEN)->postJson(self::NOTIFY, ['action' => 'delete_everything']));
    }

    public function testTheHoldingAdjusterCanVerifyADocumentAndTheClaimantIsTold(): void
    {
        $told = $this->rows('notifications', "user_id = 1 AND claim_id = 101 AND type = 'doc_verified'");   // the seed already has one

        $response = $this->signIn(self::BRIAN)->postJson(self::DOCS, ['action' => 'verify', 'id' => 3]);

        $this->assertStatus(200, $response);
        $this->assertSame(1, $this->rows('claim_documents', 'id = 3 AND is_verified = 1 AND verified_by = 2 AND verified_at IS NOT NULL'));
        $this->assertSame($told + 1, $this->rows('notifications', "user_id = 1 AND claim_id = 101 AND type = 'doc_verified'"));
        $this->assertSame(1, $this->rows('audit_log', "action = 'document.verified' AND entity_id = 3"));
    }

    public function testVerifyingTwiceDoesNotNotifyTwice(): void
    {
        $brian = $this->signIn(self::BRIAN);
        $brian->postJson(self::DOCS, ['action' => 'verify', 'id' => 3]);
        $afterFirst = $this->rows('notifications', "claim_id = 101 AND type = 'doc_verified'");

        $this->assertStatus(200, $brian->postJson(self::DOCS, ['action' => 'verify', 'id' => 3]));

        $this->assertSame($afterFirst, $this->rows('notifications', "claim_id = 101 AND type = 'doc_verified'"), 'the second verify must not notify again');
    }

    public function testAnAdjusterCannotVerifyADocumentOnAnotherDesk(): void
    {
        $this->assertStatus(403, $this->signIn(self::GRACE)->postJson(self::DOCS, ['action' => 'verify', 'id' => 3]));
        $this->assertSame(0, (int)$this->value('SELECT is_verified FROM claim_documents WHERE id = 3'));
    }

    public function testUnknownDocumentsAndActionsAreRefused(): void
    {
        $brian = $this->signIn(self::BRIAN);

        $this->assertStatus(404, $brian->postJson(self::DOCS, ['action' => 'verify', 'id' => 99999]));
        $this->assertStatus(422, $brian->postJson(self::DOCS, ['action' => 'delete', 'id' => 3]));
    }
}
