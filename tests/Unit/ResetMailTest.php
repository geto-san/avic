<?php
declare(strict_types=1);

namespace Avic\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ResetMailTest extends TestCase
{
    private function runMail(string $url, string $from): array
    {
        $source = dirname(__DIR__, 2) . '/config/auth/reset-mail.php';
        $code = 'putenv("AVIC_ENV=production"); putenv(' . var_export('AVIC_PUBLIC_URL=' . $url, true) . ');'
            . 'putenv(' . var_export('AVIC_MAIL_FROM=' . $from, true) . '); require ' . var_export($source, true) . ';'
            . 'try { var_export(sendPasswordResetMail("recipient@example.invalid", str_repeat("a", 64))); }'
            . 'catch (Throwable $e) { echo $e->getMessage(); exit(2); }';
        // No email leaves the machine: the transport always fails locally.
        $process = proc_open([PHP_BINARY, '-d', 'sendmail_path=/bin/false', '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    public function testProductionDeliveryFailureNeverLogsResetToken(): void
    {
        [$status, $out, $err] = $this->runMail('https://claims.example.test', 'no-reply@example.test');
        $this->assertSame(0, $status);
        $this->assertSame('false', $out);
        $this->assertStringContainsString('delivery failed', $err);
        $this->assertStringNotContainsString(str_repeat('a', 64), $out . $err);
        $this->assertStringNotContainsString('token=', $out . $err);
    }

    public function testProductionRequiresConfiguredHttpsOriginAndSender(): void
    {
        foreach ([['', ''], ['http://example.test', 'no-reply@example.test'], ['https://example.test', "bad\r\nBcc: other@example.test"]] as [$url, $from]) {
            [$status, $out] = $this->runMail($url, $from);
            $this->assertSame(2, $status);
            $this->assertStringContainsString('Configure AVIC_PUBLIC_URL', $out);
        }
    }
}
