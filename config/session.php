<?php
declare(strict_types=1);

/**
 * Boot a secure PHP session for the.
 *
 * The cookie is HTTP-only (never readable by JS), SameSite=Lax (a cross-site
 * POST form cannot ride the session), and Secure by default, so it is only
 * ever sent over HTTPS. Pass $remember = true to extend the cookie lifetime.
 *
 * Local development over plain http:// (e.g. `php -S 127.0.0.1:8080`) can opt
 * out explicitly with AVIC_ALLOW_INSECURE_COOKIES=1 in the environment. That is
 * deliberately a named switch and not something inferred from the request, so a
 * production deploy fails closed if it is ever served without TLS.
 */
function avicSession(bool $remember = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $options = [
        'lifetime' => $remember ? 2592000 : 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    // Dev-only escape hatch (see docblock). Never set this on a real deploy.
    $devFlag = getenv('AVIC_ALLOW_INSECURE_COOKIES') ?: ($_SERVER['AVIC_ALLOW_INSECURE_COOKIES'] ?? '');
    if ($devFlag === '1') {
        $options['secure'] = false;
    }

    session_name('AVICSESSID');
    session_set_cookie_params($options);
    session_start();
}
