<?php
declare(strict_types=1);

// Router for php -S. Deny source/config/test downloads and implicit index fallback.
// Apache applies the equivalent public-path rules in .htaccess.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$root = dirname(__DIR__);
$public = $path === '/' || $path === '/index.html' || $path === '/robots.txt'
    || preg_match('~^/assets/(?:css/[a-z-]+\.css|js/[a-z-]+\.js|favicon\.svg)$~D', $path)
    || preg_match('~^/pages/(?:auth|claimant|adjuster|garage|errors)/[a-z0-9-]+\.html$~D', $path)
    || preg_match('~^/config/auth/(?:login|logout|register|forgot-password|reset-password|session-token)\.php$~D', $path)
    || preg_match('~^/config/api/(?:bootstrap|coverage-checks|claims|claims-assign|claims-decision|claims-status|claims-pdf|documents|estimates|estimates-decision|notifications|payouts-update|policies|health|settlements|complaints|document-responses|policy-registrations)\.php$~D', $path)
    || $path === '/config/uploads.php';
if (!$public || ($path !== '/' && !is_file($root . $path))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    return true;
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
return false;
