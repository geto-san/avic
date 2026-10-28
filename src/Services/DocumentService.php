<?php
declare(strict_types=1);

namespace Avic\Services;

use Avic\Core\Db;
use Avic\Core\Env;
use Avic\Core\HttpException;
use Avic\Core\RuleViolation;
use Avic\Domain\Catalog;

/** Claim documents. Files live outside public/ and are only ever served through the app. */
final class DocumentService
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    public static function dir(): string
    {
        return rtrim(Env::get('AVIC_UPLOAD_DIR', AVIC_ROOT . '/storage/uploads'), '/');
    }

    /**
     * @param array $file one entry of $_FILES
     * @param callable|null $mover replaces move_uploaded_file (tests only)
     */
    public static function store(int $claimId, string $docType, array $file, array $actor, ?callable $mover = null): int
    {
        if (!isset(Catalog::DOC_TYPES[$docType])) {
            throw new RuleViolation('Choose what kind of document this is.');
        }
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuleViolation('Choose a file to upload.');
        }
        if ($error !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuleViolation('The file could not be uploaded (maximum 5 MB).');
        }
        $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime])) {
            throw new RuleViolation('Only JPEG, PNG and PDF files are accepted.');
        }
        $claim = Db::one('SELECT status FROM claims WHERE id = ?', [$claimId]);
        if (!$claim) {
            throw new HttpException(404);
        }
        if ($claim['status'] === 'closed') {
            throw new RuleViolation('Documents cannot be added to a closed claim.');
        }
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuleViolation('The upload folder is not available.');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        if (!($mover ?? 'move_uploaded_file')($file['tmp_name'], $dir . '/' . $stored)) {
            throw new RuleViolation('The file could not be saved.');
        }
        $name = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]/u', '_', basename((string)$file['name'])) ?? 'file', 0, 200);
        $id = Db::insert(
            'INSERT INTO documents (claim_id, uploaded_by, doc_type, original_name, stored_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$claimId, $actor['id'], $docType, $name, $stored, $mime, (int)$file['size']]
        );
        AuditLog::record($claimId, 'document.uploaded', $actor, ['name' => $name, 'type' => $docType], 'document', $id);
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public static function forClaim(int $claimId): array
    {
        return Db::all(
            'SELECT d.*, u.full_name AS uploader_name FROM documents d JOIN users u ON u.id = d.uploaded_by WHERE d.claim_id = ? ORDER BY d.id DESC',
            [$claimId]
        );
    }

    /** The document row + absolute path, after checking the viewer may see its claim. */
    public static function open(int $id, array $viewer): array
    {
        $d = Db::one('SELECT * FROM documents WHERE id = ?', [$id]);
        if (!$d) {
            throw new HttpException(404);
        }
        ClaimService::get((int)$d['claim_id'], $viewer);
        $path = self::dir() . '/' . $d['stored_name'];
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|pdf)$/', $d['stored_name']) || !is_file($path)) {
            throw new HttpException(404);
        }
        return [$d, $path];
    }
}
