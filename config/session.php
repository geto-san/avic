<?php
declare(strict_types=1);

/* Boot a secure PHP session for the app. */
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

    // Dev-only escape hatch (see docblock). A real environment variable
    // only — never read from $_SERVER/the request — so nothing a client
    // sends can influence it. Never set this on a real deploy.
    if (getenv('AVIC_ALLOW_INSECURE_COOKIES') === '1' && strtolower((string)getenv('AVIC_ENV')) !== 'production') {
        $options['secure'] = false;
    }

    session_name('AVICSESSID');
    session_set_cookie_params($options);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
