<?php
declare(strict_types=1);

/**
 * Where uploaded files live on disk and how a stored file_path maps back to a
 * real file. Pure functions with no side effects, split out of _helpers.php so
 * they can be unit tested without a database.
 */

/**
 * Where uploaded files are stored. Deliberately OUTSIDE the web root so a file
 * can only ever be fetched through config/uploads.php (which checks the role).
 * Set AVIC_UPLOAD_DIR to an absolute path on any real deploy; the default is a
 * sibling of the project folder, which is outside the docroot when you serve
 * the project folder itself (e.g. `php -S` from the repo root).
 */
function uploadDir(): string
{
    $env = getenv('AVIC_UPLOAD_DIR');
    if (is_string($env) && $env !== '') {
        return rtrim($env, '/\\');
    }
    return dirname(__DIR__, 3) . '/avic-uploads';
}

/** Before the move, files lived in <project>/uploads/. Still readable, never written. */
function legacyUploadDir(): string
{
    return dirname(__DIR__, 2) . '/uploads';
}

/**
 * Turn a claim_documents.file_path into a real file on disk, or null.
 * Only the file NAME is trusted (basename), so neither a bare name nor an old
 * "uploads/<name>" value can point outside the upload folders.
 */
function uploadResolvePath(string $filePath): ?string
{
    $name = basename(str_replace('\\', '/', $filePath));
    if ($name === '' || $name === '.' || $name === '..') {
        return null;
    }
    foreach ([uploadDir(), legacyUploadDir()] as $dir) {
        $candidate = $dir . '/' . $name;
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/** Whitelist of MIME -> extension used for both validation and mime sniffing. */
function uploadExtFromMime(string $mime): ?string
{
    $map = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    return $map[strtolower($mime)] ?? null;
}
