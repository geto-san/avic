<?php
declare(strict_types=1);

/**
 * Boot a secure PHP session for the.
 *
 * The cookie is HTTP-only (never readable by JS), SameSite=Lax (a cross-site
 * POST form cannot ride the session), and Secure whenever the request arrives
 * over HTTPS. Pass $remember = true to extend the cookie lifetime.
 */
function avic_session(bool $remember = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('AVICSESSID');
    session_set_cookie_params([
        'lifetime' => $remember ? 2592000 : 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}