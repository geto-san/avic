<?php
declare(strict_types=1);

namespace Avic\Services;

use Avic\Core\Db;

final class AuditLog
{
    public static function record(?int $claimId, string $action, ?array $actor, array $detail = [], ?string $entityType = null, ?int $entityId = null): void
    {
        Db::run(
            'INSERT INTO audit_log (claim_id, user_id, action, entity_type, entity_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $claimId, $actor['id'] ?? null, $action, $entityType, $entityId,
                $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    /** @return list<array<string,mixed>> newest first, with the actor's name and decoded detail */
    public static function forClaim(int $claimId, bool $statusOnly = false): array
    {
        $sql = 'SELECT a.*, u.full_name AS actor_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.claim_id = ?';
        if ($statusOnly) {
            $sql .= " AND a.action IN ('claim.created','claim.status_changed')";
        }
        $rows = Db::all($sql . ' ORDER BY a.id DESC', [$claimId]);
        foreach ($rows as &$r) {
            $r['detail'] = $r['detail'] ? (json_decode($r['detail'], true) ?: []) : [];
        }
        return $rows;
    }
}
