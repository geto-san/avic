<?php
declare(strict_types=1);

namespace Avic\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * config/db/db_connection.php decides, from the environment, whether the app
 * may start. Run it as a child process: it exit()s on a bad configuration.
 */
final class DbConfigTest extends TestCase
{
    /**
     * @param array<string,string> $env
     * @return array{int,string,string} exit code, stdout, stderr
     */
    private function runScript(string $script, array $env): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/' . $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            ['PATH' => (string)getenv('PATH')] + $env
        );
        $out = (string)stream_get_contents($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    public function testProductionRefusesToStartWithoutAPassword(): void
    {
        [$code, $out, $err] = $this->runScript('config/db/db_connection.php', ['AVIC_ENV' => 'production']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Server is not configured correctly', $out);
        $this->assertStringContainsString('AVIC_DB_PASS must be set', $err);
    }

    public function testProductionRefusesTheBuiltInDevPassword(): void
    {
        [$code, $out, $err] = $this->runScript('config/db/db_connection.php', ['AVIC_ENV' => 'production', 'AVIC_DB_PASS' => '12345678']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Server is not configured correctly', $out);
        $this->assertStringContainsString('development database password', $err);
    }

    public function testTheProductionErrorNeverEchoesTheCredentialOrTheReason(): void
    {
        [, $out] = $this->runScript('config/db/db_connection.php', ['AVIC_ENV' => 'production', 'AVIC_DB_PASS' => '12345678']);
        $this->assertStringNotContainsString('12345678', $out);
        $this->assertStringNotContainsString('AVIC_DB_PASS', $out);
    }

    public function testANonNumericPortIsRejected(): void
    {
        [$code, , $err] = $this->runScript('config/db/db_connection.php', ['AVIC_DB_PORT' => '33;06']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('AVIC_DB_PORT must be a number', $err);
    }

    public function testAnUnreachableDatabaseExitsNonZero(): void
    {
        [$code, $out] = $this->runScript('config/db/db_connection.php', [
            'AVIC_DB_HOST' => '127.0.0.1', 'AVIC_DB_PORT' => '1', 'AVIC_DB_PASS' => 'x',
        ]);
        $this->assertSame(1, $code, 'scripts and CI must see a failed connection as a failure');
        $this->assertStringContainsString('Database connection failed', $out);
    }

    public function testTheDemoSeedRefusesToRunInProduction(): void
    {
        [$code, , $err] = $this->runScript('config/db/seed-demo.php', ['AVIC_ENV' => 'production']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Refusing to seed demo data', $err);
    }
}
