<?php
declare(strict_types=1);

namespace Avic\Domain;

use Avic\Core\Db;

/** Picks the adjuster for a new claim using the ordered rules in config/rules.php. */
final class Assignment
{
    public static function pick(string $lossType, string $region): ?int
    {
        $facts = ['loss_type' => $lossType, 'loss_region' => $region];
        $columns = ['specialty' => 'u.specialty', 'region' => 'u.region'];

        foreach (Rules::config()['assignment'] as $rule) {
            $where = ["u.role = 'adjuster'", "u.status = 'active'"];
            $params = [];
            foreach ($rule['match'] as $column => $fact) {
                $where[] = $columns[$column] . ' = ?';
                $params[] = $facts[$fact];
            }
            $id = Db::val(
                "SELECT u.id FROM users u WHERE " . implode(' AND ', $where) . "
                 ORDER BY (SELECT COUNT(*) FROM claims c WHERE c.assigned_to = u.id AND c.status <> 'closed') ASC, u.id ASC
                 LIMIT 1",
                $params
            );
            if ($id !== null) {
                return (int)$id;
            }
        }
        return null;
    }
}
