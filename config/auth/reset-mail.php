<?php
declare(strict_types=1);

/** Reset URLs use configured application identity, never the request's Host header. */
function resetMailConfig(): array
{
    $production = strtolower((string)getenv('AVIC_ENV')) === 'production';
    $base = rtrim((string)(getenv('AVIC_PUBLIC_URL') ?: ($production ? '' : 'http://127.0.0.1:8080')), '/');
    $parts = parse_url($base);
    $from = (string)(getenv('AVIC_MAIL_FROM') ?: ($production ? '' : 'no-reply@localhost.test'));
    if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', $production ? ['https'] : ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || !filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $base . $from)) {
        throw new RuntimeException('Configure AVIC_PUBLIC_URL and AVIC_MAIL_FROM before sending password resets.');
    }
    return [$base, $from];
}

function sendPasswordResetMail(string $email, string $token): bool
{
    [$base, $from] = resetMailConfig();
    $link = $base . '/pages/auth/reset-password.html?token=' . rawurlencode($token);
    $sent = @mail($email, 'Reset your AVIC password',
        "Use this link within the next hour to choose a new password:\n\n$link\n\nIf you did not request this, ignore this email.",
        ['From' => $from, 'Content-Type' => 'text/plain; charset=UTF-8']);
    if (!$sent) {
        // Delivery failure never exposes a bearer token in production logs.
        if (strtolower((string)getenv('AVIC_ENV')) === 'production') {
            error_log('AVIC: password reset email delivery failed. Check the mail transport.');
        } else {
            error_log('[password reset development] ' . $link);
        }
    }
    return $sent;
}
