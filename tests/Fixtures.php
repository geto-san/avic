<?php
declare(strict_types=1);

use Avic\Core\Db;

/** Builders for test data. All tests run against a database whose name ends in _test. */
final class Fx
{
    private static int $n = 0;

    public static function user(string $role = 'claimant', array $over = []): array
    {
        $n = ++self::$n;
        $row = $over + [
            'full_name' => ucfirst($role) . " $n", 'email' => "$role$n@test.ug", 'phone' => null,
            'password_hash' => password_hash('Passw0rd!', PASSWORD_BCRYPT, ['cost' => 4]),
            'role' => $role, 'region' => null, 'specialty' => null, 'status' => 'active',
        ];
        $id = Db::insert(
            'INSERT INTO users (full_name, email, phone, password_hash, role, region, specialty, status) VALUES (?,?,?,?,?,?,?,?)',
            [$row['full_name'], $row['email'], $row['phone'], $row['password_hash'], $row['role'], $row['region'], $row['specialty'], $row['status']]
        );
        return ['id' => $id] + $row;
    }

    public static function adjuster(array $over = []): array
    {
        return self::user('adjuster', $over);
    }

    public static function policy(?int $userId = null, array $over = []): array
    {
        $n = ++self::$n;
        $row = $over + [
            'policy_number' => "POL-T$n", 'user_id' => $userId, 'insured_name' => 'Test Insured',
            'vehicle_make' => 'Toyota', 'vehicle_model' => 'Corolla', 'vehicle_year' => 2018,
            'vehicle_plate' => "UAA {$n}T", 'coverage_type' => 'comprehensive', 'coverage_limit' => 20000000,
            'start_date' => date('Y-m-d', strtotime('-200 days')), 'end_date' => date('Y-m-d', strtotime('+160 days')),
            'status' => 'active',
        ];
        $id = Db::insert(
            'INSERT INTO policies (policy_number, user_id, insured_name, vehicle_make, vehicle_model, vehicle_year, vehicle_plate, coverage_type, coverage_limit, start_date, end_date, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$row['policy_number'], $row['user_id'], $row['insured_name'], $row['vehicle_make'], $row['vehicle_model'], $row['vehicle_year'],
             $row['vehicle_plate'], $row['coverage_type'], $row['coverage_limit'], $row['start_date'], $row['end_date'], $row['status']]
        );
        return ['id' => $id] + $row;
    }

    /** Valid FNOL input; override any field. */
    public static function fnolInput(array $over = []): array
    {
        return $over + [
            'loss_date' => date('Y-m-d', strtotime('-3 days')), 'loss_type' => 'collision', 'loss_region' => 'central',
            'loss_location' => 'Jinja Road, Kampala', 'description' => 'Rear-ended at the traffic lights.', 'police_ref' => '',
            'exposures' => [['type' => 'own_vehicle', 'party' => 'Test Insured', 'description' => 'Rear bumper']],
        ];
    }

    /** A claim on a fresh policy, reported by its claimant. Returns [claimId, adjuster, claimant, policy]. */
    public static function claim(array $over = [], ?array $adjuster = null): array
    {
        $adjuster ??= self::adjuster(['region' => 'central']);
        $claimant = self::user();
        $policy = self::policy($claimant['id']);
        $id = \Avic\Services\ClaimService::fnol($policy, self::fnolInput($over), $claimant);
        return [$id, $adjuster, $claimant, $policy];
    }

    public static function exposureId(int $claimId): int
    {
        return (int)Db::val('SELECT id FROM exposures WHERE claim_id = ? ORDER BY id LIMIT 1', [$claimId]);
    }

    /** Complete the "Verify policy coverage" activity => claim moves to investigating. */
    public static function investigate(int $claimId, array $adjuster): void
    {
        $a = (int)Db::val("SELECT id FROM activities WHERE claim_id = ? AND template_key = 'verify_coverage'", [$claimId]);
        \Avic\Services\ActivityService::complete($a, $adjuster);
    }

    public static function status(int $claimId): string
    {
        return (string)Db::val('SELECT status FROM claims WHERE id = ?', [$claimId]);
    }
}
