<?php
declare(strict_types=1);

/* Boot a secure PHP session for the app. */
function avicSession(bool $remember = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('AVICSESSID');
    session_set_cookie_params([
        'lifetime' => $remember ? 2592000 : 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
