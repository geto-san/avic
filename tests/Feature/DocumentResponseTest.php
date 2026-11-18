<?php
declare(strict_types=1);
namespace Avic\Tests\Feature;

final class DocumentResponseTest extends FeatureTestCase
{
    private function requestTwo(): array
    {
        $this->assertStatus(200, $this->signIn(self::BRIAN)->postJson('config/api/claims-decision.php', [
            'claim_id'=>101,'decision'=>'request_docs','documents'=>[
                ['document_type'=>'police_report'],['document_type'=>'other','document_name'=>'Driving licence']
            ]
        ]));
        return $this->db()->query('SELECT id FROM requested_documents ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function testEachRequestedDocumentIsAnsweredWithoutEditingClaimDetails(): void
    {
        $ids = $this->requestTwo();
        $before = $this->db()->query('SELECT incident_date, incident_description, submitted_at, adjuster_id FROM claims WHERE id = 101')->fetch();
        $owner = $this->signIn(self::DOREEN); $png = $this->pngFile();
        try {
            foreach ($ids as $index=>$id) {
                $this->assertStatus(200, $owner->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id], [['path'=>$png,'type'=>'image/png','name'=>'scan.png']]));
                $this->assertSame($index === 0 ? 'pending_docs' : 'submitted', $this->claimStatus(101));
            }
        } finally { unlink($png); }
        $this->assertSame($before, $this->db()->query('SELECT incident_date, incident_description, submitted_at, adjuster_id FROM claims WHERE id = 101')->fetch());
        $this->assertSame('police_report', $this->value('SELECT d.doc_type FROM claim_documents d JOIN requested_documents r ON r.document_id = d.id WHERE r.id = ?', [$ids[0]]));
        $this->assertSame('answered', $this->value('SELECT status FROM claim_document_requests WHERE claim_id = 101'));
    }

    public function testResponsesArePrivateAndCannotBeRepeated(): void
    {
        $id = $this->requestTwo()[0]; $png = $this->pngFile();
        try {
            $files = [['path'=>$png,'type'=>'image/png','name'=>'scan.png']];
            $this->assertStatus(403, $this->signIn(self::ALEX)->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id], $files));
            $this->assertStatus(403, $this->signIn(self::KIGONGO)->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id], $files));
            $owner = $this->signIn(self::DOREEN);
            $this->assertStatus(422, $owner->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id]));
            $this->assertStatus(200, $owner->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id], $files));
            $this->assertStatus(409, $owner->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id], $files));
        } finally { unlink($png); }
    }

    public function testRejectedFileLeavesRequestOpen(): void
    {
        $id = $this->requestTwo()[0]; $file = tempnam(sys_get_temp_dir(), 'avic-invalid-'); file_put_contents($file, '<?php echo "bad";');
        try {
            $this->assertStatus(422, $this->signIn(self::DOREEN)->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id], [['path'=>$file,'type'=>'image/png','name'=>'fake.png']]));
            $this->assertNull($this->value('SELECT document_id FROM requested_documents WHERE id = ?', [$id]));
            $this->assertSame('pending_docs', $this->claimStatus(101));
        } finally { unlink($file); }
    }

    public function testLegacyRequestCanBeAnsweredWithoutTheClaimWizard(): void
    {
        $png = $this->pngFile();
        try {
            $this->assertStatus(200, $this->signIn(self::ALEX)->postMultipart('config/api/document-responses.php', ['claim_id'=>104], [['path'=>$png,'type'=>'image/png','name'=>'supporting-document.png']]));
            $this->assertSame('submitted', $this->claimStatus(104));
            $this->assertSame(1, $this->rows('requested_documents', 'document_id IS NOT NULL'));
        } finally { unlink($png); }
    }

    public function testResponseRollsBackAndRemovesFileWhenNotificationFails(): void
    {
        $id = $this->requestTwo()[0]; $png = $this->pngFile();
        $owner = $this->signIn(self::DOREEN);
        $this->db()->exec("CREATE TRIGGER fail_document_notice BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test failure'");
        try {
            $this->assertStatus(500, $owner->postMultipart('config/api/document-responses.php', ['requested_document_id'=>$id], [['path'=>$png,'type'=>'image/png','name'=>'scan.png']]));
            $this->assertNull($this->value('SELECT document_id FROM requested_documents WHERE id = ?', [$id]));
            $this->assertSame([], glob(\Avic\Tests\Support\TestServer::uploadDir() . '/*'));
        } finally { $this->db()->exec('DROP TRIGGER fail_document_notice'); unlink($png); }
    }

    public function testDocumentChoicesAreValidatedOnTheServer(): void
    {
        $desk = $this->signIn(self::BRIAN);
        foreach ([[['document_type'=>'other']], [['document_type'=>'unknown']], [['document_type'=>[]]]] as $items) {
            $this->assertStatus(422, $desk->postJson('config/api/claims-decision.php', ['claim_id'=>101,'decision'=>'request_docs','documents'=>$items]));
        }
        $this->assertSame('under_review', $this->claimStatus(101));
        $this->assertSame(0, $this->rows('requested_documents'));
    }
}
