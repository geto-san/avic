<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

/** Sign-in, registration, lockout, sign-out and password reset (config/auth/*). */
final class AuthTest extends FeatureTestCase
{
    private const LOGIN = 'config/auth/login.php';
    private const REGISTER = 'config/auth/register.php';
    private const FORGOT = 'config/auth/forgot-password.php';
    private const RESET = 'config/auth/reset-password.php';

    /** @return array<string,string> */
    private function newClaimant(array $override = []): array
    {
        return $override + [
            'role' => 'claimant', 'full_name' => 'Test Person', 'email' => 'test.person@example.ug',
            'phone' => '+256 700 123 456', 'password' => 'Str0ngPassw0rd', 'password2' => 'Str0ngPassw0rd',
        ];
    }

    public function testSigningInReturnsTheUserButNoSecrets(): void
    {
        $response = $this->anonymous()->login(self::DOREEN);

        $this->assertStatus(200, $response);
        $this->assertSame('claimant', $response->json()['user']['role']);
        $this->assertSame(self::DOREEN, $response->json()['user']['email']);
        $this->assertStringNotContainsString('password', strtolower($response->body));
    }

    public function testWrongPasswordAndUnknownEmailAreIndistinguishable(): void
    {
        $wrong = $this->anonymous()->login(self::DOREEN, 'not-the-password');
        $unknown = $this->anonymous()->login('nobody@example.ug', 'not-the-password');

        $this->assertStatus(401, $wrong);
        $this->assertStatus(401, $unknown);
        $this->assertSame($wrong->message(), $unknown->message(), 'the message must not reveal which emails exist');
    }

    public function testMissingOrMalformedCredentialsAreA400(): void
    {
        $client = $this->anonymous();
        $this->assertStatus(400, $client->postJson(self::LOGIN, ['email' => self::DOREEN, 'password' => '']));
        $this->assertStatus(400, $client->postJson(self::LOGIN, ['email' => 'not-an-email', 'password' => 'x']));
        $this->assertStatus(400, $client->postJson(self::LOGIN, []));
    }

    public function testPendingAndSuspendedAccountsCannotSignIn(): void
    {
        foreach ([[self::PRIDE, 'pending'], [self::KATO, 'suspended']] as [$email, $status]) {
            $client = $this->anonymous();
            $response = $client->login($email);

            $this->assertStatus(403, $response, $email);
            $this->assertStringContainsString($status, $response->message());
            $this->assertStatus(401, $client->get('config/api/bootstrap.php'), "$email must not get a session");
        }
    }

    public function testFiveFailedAttemptsLockTheAccountEvenForTheRightPassword(): void
    {
        $client = $this->anonymous();
        for ($i = 1; $i <= 5; $i++) {
            $this->assertStatus(401, $client->login(self::DOREEN, 'wrong-' . $i), "attempt $i");
        }

        $this->assertStatus(429, $client->login(self::DOREEN), 'the correct password is refused while locked');
        $this->assertStatus(200, $this->anonymous()->login(self::ALEX), 'other accounts are unaffected');
    }

    public function testEveryAttemptIsLogged(): void
    {
        $client = $this->anonymous();
        $client->login(self::DOREEN, 'wrong');
        $client->login(self::DOREEN);

        $this->assertSame(1, $this->rows('login_attempts', "email = ? AND ok = 0", [self::DOREEN]));
        $this->assertSame(1, $this->rows('login_attempts', "email = ? AND ok = 1", [self::DOREEN]));
    }

    public function testTheApiNeedsASession(): void
    {
        $this->assertStatus(401, $this->anonymous()->get('config/api/bootstrap.php'));
    }

    public function testSigningOutEndsTheSession(): void
    {
        $doreen = $this->signIn(self::DOREEN);
        $this->assertStatus(200, $doreen->get('config/api/bootstrap.php'));

        $this->assertStatus(204, $doreen->postJson('config/auth/logout.php', []));

        $this->assertStatus(401, $doreen->get('config/api/bootstrap.php'));
    }

    public function testOnlyPostIsAcceptedForSignIn(): void
    {
        $this->assertStatus(405, $this->anonymous()->get(self::LOGIN));
    }

    public function testRegisteringAClaimantLetsThemSignIn(): void
    {
        $client = $this->anonymous();
        $this->assertStatus(201, $client->postJson(self::REGISTER, $this->newClaimant()));

        $this->assertStatus(200, $client->login('test.person@example.ug', 'Str0ngPassw0rd'));
        $hash = (string)$this->value("SELECT password_hash FROM users WHERE email = 'test.person@example.ug'");
        $this->assertNotSame('Str0ngPassw0rd', $hash);
        $this->assertTrue(password_verify('Str0ngPassw0rd', $hash), 'stored as a real password hash');
    }

    public function testADuplicateEmailIsRefusedWhateverItsCase(): void
    {
        $response = $this->anonymous()->postJson(self::REGISTER, $this->newClaimant(['email' => strtoupper(self::DOREEN)]));

        $this->assertStatus(409, $response);
        $this->assertArrayHasKey('email', $response->json()['errors']);
        $this->assertSame(1, $this->rows('users', 'email = ?', [self::DOREEN]));
    }

    public function testRegistrationValidatesEveryField(): void
    {
        $response = $this->anonymous()->postJson(self::REGISTER, []);

        $this->assertStatus(400, $response);
        foreach (['role', 'full_name', 'email', 'phone', 'password'] as $field) {
            $this->assertArrayHasKey($field, $response->json()['errors'], $field);
        }
    }

    public function testPasswordsMustBeLongEnoughAndMatch(): void
    {
        $client = $this->anonymous();

        $short = $client->postJson(self::REGISTER, $this->newClaimant(['password' => 'short', 'password2' => 'short']));
        $this->assertStatus(400, $short);
        $this->assertArrayHasKey('password', $short->json()['errors']);

        $mismatch = $client->postJson(self::REGISTER, $this->newClaimant(['password2' => 'Different1234']));
        $this->assertStatus(400, $mismatch);
        $this->assertArrayHasKey('password2', $mismatch->json()['errors']);
    }

    public function testAGarageMustGiveAnAddressAndALicence(): void
    {
        $response = $this->anonymous()->postJson(self::REGISTER, $this->newClaimant(['role' => 'garage']));

        $this->assertStatus(400, $response);
        $this->assertArrayHasKey('garage_address', $response->json()['errors']);
        $this->assertArrayHasKey('trading_licence', $response->json()['errors']);
    }

    public function testNobodyCanRegisterThemselvesAsAnAdjuster(): void
    {
        $response = $this->anonymous()->postJson(self::REGISTER, $this->newClaimant(['role' => 'adjuster']));

        $this->assertStatus(400, $response);
        $this->assertSame(0, $this->rows('users', "email = 'test.person@example.ug'"));
    }

    public function testThePasswordResetFlowWorksOnceAndOnlyOnce(): void
    {
        $client = $this->anonymous();
        $this->assertStatus(200, $client->postJson(self::FORGOT, ['email' => self::DOREEN]));
        $token = (string)$this->value('SELECT token FROM password_resets WHERE email = ?', [self::DOREEN]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);

        $this->assertStatus(200, $client->postJson(self::RESET, ['token' => $token, 'password' => 'BrandNew-Pass1', 'password2' => 'BrandNew-Pass1']));

        $this->assertStatus(401, $this->anonymous()->login(self::DOREEN), 'the old password is dead');
        $this->assertStatus(200, $this->anonymous()->login(self::DOREEN, 'BrandNew-Pass1'));
        $this->assertStatus(400, $client->postJson(self::RESET, ['token' => $token, 'password' => 'Another-Pass12', 'password2' => 'Another-Pass12']), 'the link is single-use');
    }

    public function testAskingAgainInvalidatesTheEarlierLink(): void
    {
        $client = $this->anonymous();
        $client->postJson(self::FORGOT, ['email' => self::DOREEN]);
        $first = (string)$this->value('SELECT token FROM password_resets WHERE email = ?', [self::DOREEN]);
        $client->postJson(self::FORGOT, ['email' => self::DOREEN]);

        $this->assertSame(1, $this->rows('password_resets', 'email = ?', [self::DOREEN]));
        $this->assertStatus(400, $client->postJson(self::RESET, ['token' => $first, 'password' => 'BrandNew-Pass1', 'password2' => 'BrandNew-Pass1']));
    }

    public function testForgotPasswordNeverRevealsWhetherAnEmailHasAnAccount(): void
    {
        $client = $this->anonymous();
        $known = $client->postJson(self::FORGOT, ['email' => self::DOREEN]);
        $unknown = $client->postJson(self::FORGOT, ['email' => 'nobody@example.ug']);
        $garbage = $client->postJson(self::FORGOT, ['email' => 'not an email']);

        $this->assertStatus(200, $known);
        $this->assertSame($known->body, $unknown->body);
        $this->assertSame($known->body, $garbage->body);
        $this->assertSame(0, $this->rows('password_resets', "email = 'nobody@example.ug'"));
    }

    public function testAnExpiredResetLinkIsRefused(): void
    {
        $this->db()->exec("INSERT INTO password_resets (email, token, expires_at) VALUES ('" . self::DOREEN . "', '" . str_repeat('a', 64) . "', NOW() - INTERVAL 1 MINUTE)");

        $response = $this->anonymous()->postJson(self::RESET, ['token' => str_repeat('a', 64), 'password' => 'BrandNew-Pass1', 'password2' => 'BrandNew-Pass1']);

        $this->assertStatus(400, $response);
        $this->assertStatus(200, $this->anonymous()->login(self::DOREEN), 'the password must not have changed');
    }

    public function testAResetNeedsAStrongEnoughMatchingPassword(): void
    {
        $this->db()->exec("INSERT INTO password_resets (email, token, expires_at) VALUES ('" . self::DOREEN . "', '" . str_repeat('b', 64) . "', NOW() + INTERVAL 1 HOUR)");
        $client = $this->anonymous();

        $this->assertStatus(422, $client->postJson(self::RESET, ['token' => str_repeat('b', 64), 'password' => 'short', 'password2' => 'short']));
        $this->assertStatus(422, $client->postJson(self::RESET, ['token' => str_repeat('b', 64), 'password' => 'LongEnough123', 'password2' => 'Different12345']));
        $this->assertStatus(400, $client->postJson(self::RESET, ['token' => '', 'password' => 'LongEnough123', 'password2' => 'LongEnough123']));
    }

    public function testTheAdjusterSeedScriptWorksNonInteractively(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/config/auth/seed-adjuster.php')
            . ' ' . escapeshellarg('Adjuster-Pass1') . ' ' . escapeshellarg('Seeded Adjuster') . ' ' . escapeshellarg('seeded@avic.ug') . ' ' . escapeshellarg('+256 700 000 009')
            . ' </dev/null 2>&1';
        exec($command, $output, $code);

        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertSame('adjuster', $this->value("SELECT role FROM users WHERE email = 'seeded@avic.ug'"));
        $this->assertStatus(200, $this->anonymous()->login('seeded@avic.ug', 'Adjuster-Pass1'));
    }
}
