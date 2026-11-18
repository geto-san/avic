<?php
declare(strict_types=1);

/* Boot a secure PHP session for the app. */
function avicSession(bool $remember = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('AVICSESSID');
    ini_set('session.cookie_samesite', 'Lax');
    // Pass Secure and HttpOnly explicitly, including for loopback development.
    session_set_cookie_params($remember ? 2592000 : 0, '/', '', true, true);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
