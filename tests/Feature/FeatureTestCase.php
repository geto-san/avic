<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

use Avic\Tests\Support\ApiClient;
use Avic\Tests\Support\Response;
use Avic\Tests\Support\TestDatabase;
use Avic\Tests\Support\TestServer;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that drive the real app over HTTP.
 *
 * Every test starts from the pristine demo seed (all tables wiped, seed-demo.php
 * re-run), so tests may change anything and never affect each other. The seed's
 * people and claims are named below — the numbers are the seed's row ids.
 */
abstract class FeatureTestCase extends TestCase
{
    protected const DOREEN    = 'doreen@example.ug';        // claimant 1: claims 101 (under review), 102 (paid), 103 (draft), 108 (approved)
    protected const ALEX      = 'alex.m@example.ug';        // claimant 5: claims 104 (docs requested), 105 (submitted), 107 (approved)
    protected const PRIDE     = 'pride.n@example.ug';       // claimant 6: account pending
    protected const KATO      = 'r.kato@example.ug';        // claimant 9: account suspended
    protected const BRIAN     = 'brian.okot@avic.ug';       // adjuster 2: claims 101, 102, 104, 106, 108
    protected const GRACE     = 'grace.a@avic.ug';          // adjuster 8: claims 105, 107
    protected const KIGONGO   = 'desk@kigongomotors.ug';    // garage 3: work orders on 101, 104, 107
    protected const SSEMWANGA = 'info@ssemwanga.ug';        // garage 7: no work orders

    protected static string $base = '';

    public static function setUpBeforeClass(): void
    {
        TestDatabase::migrate();
        self::$base = TestServer::start();
    }

    protected function setUp(): void
    {
        TestDatabase::resetAndSeed();
        TestServer::clearUploads();
    }

    protected function anonymous(): ApiClient
    {
        return new ApiClient(self::$base);
    }

    protected function signIn(string $email): ApiClient
    {
        $client = new ApiClient(self::$base);
        $response = $client->login($email);
        $this->assertSame(200, $response->status, "signing in as $email failed: " . $response->body);
        return $client;
    }

    protected function db(): PDO
    {
        return TestDatabase::connect();
    }

    /** @param list<scalar> $params @return mixed first column of the first row, or false */
    protected function value(string $sql, array $params = []): mixed
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    protected function claimStatus(int $claimId): string
    {
        return (string)$this->value('SELECT status FROM claims WHERE id = ?', [$claimId]);
    }

    protected function rows(string $table, string $where = '1', array $params = []): int
    {
        return (int)$this->value("SELECT COUNT(*) FROM `$table` WHERE $where", $params);
    }

    protected function assertStatus(int $expected, Response $response, string $what = ''): void
    {
        $this->assertSame($expected, $response->status, ($what !== '' ? "$what: " : '') . 'body was ' . $response->body);
    }

    /** @return array<string,mixed> a complete, valid claim form (override what a test cares about) */
    protected function claimForm(array $override = []): array
    {
        return $override + [
            'mode' => 'submit',
            'policy_id' => 1,
            'claim_type' => 'collision',
            'incident_date' => '2026-08-28',
            'incident_location' => 'Kampala Road',
            'incident_description' => 'Hit while parked.',
            'estimated_damage' => '1500000',
        ];
    }

    /** A 1x1 PNG on disk, for upload tests. */
    protected function pngFile(): string
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'avic-png-');
        file_put_contents($path, (string)base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
        return $path;
    }
}
