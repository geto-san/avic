<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

use Avic\Tests\Support\TestServer;

/** Document upload, storage location and the access-checked download proxy (uploads.php). */
final class UploadTest extends FeatureTestCase
{
    private const SUBMIT = 'config/api/claims.php';

    /** @return array{int,string} the new document's id and its stored name */
    private function uploadPng(\Avic\Tests\Support\ApiClient $doreen, string $name = 'accident-photo.png'): array
    {
        $png = $this->pngFile();
        $response = $doreen->postMultipart(self::SUBMIT, $this->claimForm(), [['path' => $png, 'type' => 'image/png', 'name' => $name]]);
        unlink($png);
        $this->assertStatus(200, $response);
        $this->assertSame(1, $response->json()['documents_saved']);

        $id = (int)$this->value('SELECT MAX(id) FROM claim_documents');
        return [$id, (string)$this->value('SELECT stored_name FROM claim_documents WHERE id = ?', [$id])];
    }

    public function testUploadsAreStoredOutsideTheWebRoot(): void
    {
        [, $stored] = $this->uploadPng($this->signIn(self::DOREEN));

        $this->assertFileExists(TestServer::uploadDir() . '/' . $stored);
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/uploads/' . $stored);
        $this->assertStatus(404, $this->anonymous()->get('uploads/' . $stored), 'a direct URL must not serve it');
    }

    public function testTheStoredPathIsJustTheStoredName(): void
    {
        [$id, $stored] = $this->uploadPng($this->signIn(self::DOREEN));

        $this->assertSame($stored, $this->value('SELECT file_path FROM claim_documents WHERE id = ?', [$id]));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{20}\.png$/', $stored, 'a random name, never the uploader\'s');
    }

    public function testTheOwnerAndTheAssignedAdjusterCanViewTheDocument(): void
    {
        $doreen = $this->signIn(self::DOREEN);
        [$id] = $this->uploadPng($doreen);
        $adjuster = (int)$this->value('SELECT adjuster_id FROM claim_documents d JOIN claims c ON c.id = d.claim_id WHERE d.id = ?', [$id]);

        $own = $doreen->get('config/uploads.php?doc=' . $id);
        $this->assertStatus(200, $own);
        $this->assertSame('image/png', $own->header('content-type'));

        $desk = $this->signIn($adjuster === 2 ? self::BRIAN : self::GRACE);
        $this->assertStatus(200, $desk->get('config/uploads.php?doc=' . $id));
    }

    public function testNobodyElseCanViewTheDocument(): void
    {
        [$id] = $this->uploadPng($this->signIn(self::DOREEN));
        $url = 'config/uploads.php?doc=' . $id;
        $otherAdjuster = (int)$this->value('SELECT adjuster_id FROM claims WHERE id = (SELECT claim_id FROM claim_documents WHERE id = ?)', [$id]) === 2 ? self::GRACE : self::BRIAN;

        $this->assertStatus(401, $this->anonymous()->get($url));
        $this->assertStatus(403, $this->signIn(self::ALEX)->get($url), 'another claimant');
        $this->assertStatus(403, $this->signIn($otherAdjuster)->get($url), 'an adjuster who does not hold the claim');
        $this->assertStatus(403, $this->signIn(self::KIGONGO)->get($url), 'a garage');
    }

    public function testTheDownloadIsHardened(): void
    {
        [$id] = $this->uploadPng($this->signIn(self::DOREEN));
        $this->db()->exec("UPDATE claim_documents SET original_name = 'ev\"il; x.png' WHERE id = $id");

        $response = $this->signIn(self::DOREEN)->get('config/uploads.php?doc=' . $id);

        $this->assertSame('nosniff', $response->header('x-content-type-options'));
        $this->assertStringContainsString('no-store', (string)$response->header('cache-control'));
        $disposition = (string)$response->header('content-disposition');
        $this->assertStringNotContainsString('ev"il', $disposition, 'a quote in the file name must not break out of the header');
        $this->assertStringContainsString('filename="ev_il_x.png"', $disposition);
    }

    public function testFilesUploadedBeforeTheMoveAreStillServed(): void
    {
        $legacy = dirname(__DIR__, 2) . '/uploads';
        $createdDir = !is_dir($legacy);
        if ($createdDir) {
            mkdir($legacy);
        }
        $png = $this->pngFile();
        copy($png, "$legacy/legacy-test.png");
        $size = filesize($png);
        unlink($png);
        try {
            $this->db()->exec("INSERT INTO claim_documents (claim_id, uploaded_by, doc_type, original_name, stored_name, file_path, file_size, mime_type)
                               VALUES (102, 1, 'other', 'old.png', 'legacy-test.png', 'uploads/legacy-test.png', $size, 'image/png')");
            $id = (int)$this->value('SELECT MAX(id) FROM claim_documents');

            $this->assertStatus(200, $this->signIn(self::DOREEN)->get('config/uploads.php?doc=' . $id));
        } finally {
            @unlink("$legacy/legacy-test.png");
            if ($createdDir) {
                @rmdir($legacy);
            }
        }
    }

    public function testASeededDocumentWithNoFileIsReportedMissingNotCrashed(): void
    {
        $this->assertStatus(404, $this->signIn(self::DOREEN)->get('config/uploads.php?doc=7'));
    }

    public function testAnUnknownDocumentIs404(): void
    {
        $this->assertStatus(404, $this->signIn(self::DOREEN)->get('config/uploads.php?doc=99999'));
    }

    public function testOnlyRealImagesAndPdfsAreKept(): void
    {
        $script = (string)tempnam(sys_get_temp_dir(), 'avic-evil-');
        file_put_contents($script, '<?php echo "owned"; ?>');
        $doreen = $this->signIn(self::DOREEN);

        $response = $doreen->postMultipart(self::SUBMIT, $this->claimForm(), [['path' => $script, 'type' => 'image/png', 'name' => 'looks-like-a.png']]);
        unlink($script);

        $this->assertStatus(200, $response, 'the claim itself still goes through');
        $this->assertSame(0, $response->json()['documents_saved'], 'a PHP file with a .png name and image/png header is still not an image');
        $this->assertSame([], glob(TestServer::uploadDir() . '/*'));
    }

    public function testAnUnwritableUploadFolderFailsTheWholeSubmissionCleanly(): void
    {
        // run a second server whose upload folder cannot be created
        $port = $this->freePort();
        $env = getenv() + [];
        $env['AVIC_UPLOAD_DIR'] = '/proc/avic-cannot-create-this';
        $env['AVIC_ALLOW_INSECURE_COOKIES'] = '1';
        $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname(__DIR__, 2)], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, dirname(__DIR__, 2), $env);
        try {
            $this->waitFor($port);
            $client = new \Avic\Tests\Support\ApiClient("http://127.0.0.1:$port");
            $this->assertSame(200, $client->login(self::DOREEN)->status);
            $before = $this->rows('claims');
            $png = $this->pngFile();

            $response = $client->postMultipart(self::SUBMIT, $this->claimForm(), [['path' => $png, 'type' => 'image/png', 'name' => 'a.png']]);
            unlink($png);

            $this->assertStatus(500, $response);
            $this->assertSame('Could not store the uploaded files right now.', $response->message());
            $this->assertSame($before, $this->rows('claims'), 'no half-saved claim');
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    private function freePort(): int
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr((string)strrchr((string)stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        return $port;
    }

    private function waitFor(int $port): void
    {
        for ($i = 0; $i < 50; $i++) {
            if ($socket = @fsockopen('127.0.0.1', $port, $e, $s, 0.2)) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }
        $this->fail('the second test server did not start');
    }
}
