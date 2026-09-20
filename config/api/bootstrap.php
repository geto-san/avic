<?php
declare(strict_types=1);

/**
 * GET config/api/bootstrap.php
 *
 * One request that hydrates the front-end AVIC.* data layer for the
 * signed-in user. The role decides the shape of the response:
 *
 *   - claimant: their policies, claims, documents, estimates, payouts, notifications
 *   - adjuster: their claims + the claimant identities, policies, docs, estimates,
 *               work orders on those claims, garages to assign, notifications
 *   - garage:   only the vehicle/damage projection of the claims it holds a
 *               work order for, its own estimates and notifications — never the
 *               claimant's identity or payout figures
 *
 * The front end stores nothing of its own: if this call fails, the app has no
 * data to render and redirects to sign-in (this is what closes the
 * client-only-guard gap).
 */

require_once __DIR__ . '/_helpers.php';

const NOTIF_BY_USER_SQL = 'SELECT * FROM notifications WHERE user_id = ';
const ORDER_CREATED_DESC = ' ORDER BY created_at DESC';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    apiJson(405, ['message' => 'Method not allowed']);
}

$user = apiUser();
$id   = (int)$user['id'];

try {
    $settings = apiSettings($conn);
    $slaDays  = (int)($settings['claims_sla_days'] ?? 14);

    $payload = [
        'me'            => [
            'id' => $id, 'uuid' => $user['uuid'], 'full_name' => $user['name'],
            'email' => $user['email'], 'phone' => null, 'role' => $user['role'],
            'status' => $user['status'],
        ],
        'users'         => [],
        'claims'        => [],
        'policies'      => [],
        'documents'     => [],
        'estimates'     => [],
        'workOrders'    => [],
        'notifications' => [],
        'payouts'       => [],
        'settings'      => $settings,
    ];

    $rowsOf = function (PDOStatement $stmt): ?array {
        return $stmt === null ? null : $stmt->fetchAll();
    };

    /* ---------------- role data ---------------- */
    $meStmt   = fn() => $conn->prepare('SELECT * FROM users WHERE id = :id')->execute(['id' => $id]);

    if ($user['role'] === 'claimant') {
        $claims = $conn->prepare('SELECT c.*, u.phone AS claimant_phone FROM claims c JOIN users u ON u.id = c.user_id WHERE c.user_id = :id ORDER BY c.created_at DESC');
        $claims->execute(['id' => $id]);
        $claims = $claims->fetchAll();
        $claimIds   = array_map('intval', array_column($claims, 'id'));
        $policyIds  = array_map('intval', array_column($claims, 'policy_id'));
        $adjustIds  = array_values(array_filter(array_unique(array_map(static fn($c) => $c['adjuster_id'], $claims))));
        $inIds      = $claimIds ? implode(',', $claimIds) : '0';

        $payload['claims']   = array_map(static fn($c) => rowClaim($c, $slaDays), $claims);
        $payload['policies'] = fetchPolicies($conn, $user['role'], $id, $policyIds);
        $payload['documents']= array_map('rowDoc', fetchRows($conn, "SELECT * FROM claim_documents WHERE claim_id IN ($inIds) ORDER BY uploaded_at"));
        $payload['estimates']= fetchRows($conn, "SELECT * FROM garage_estimates WHERE claim_id IN ($inIds) ORDER BY created_at");
        $payload['notifications'] = fetchRows($conn, NOTIF_BY_USER_SQL . $id . ORDER_CREATED_DESC);
        $payload['payouts']  = fetchRows($conn, "SELECT * FROM payouts WHERE claim_id IN ($inIds)");
        $vignettes = [meRow($user)];
        foreach ($adjustIds as $aid) {
            $vignettes[] = ['id' => $aid, 'full_name' => null]; // filled below
        }
        $payload['users'] = $vignettes;
        fillMultiple($conn, $payload['users'], $adjustIds);

    } elseif ($user['role'] === 'adjuster') {
        $claims = $conn->prepare('SELECT c.*, u.phone AS claimant_phone FROM claims c JOIN users u ON u.id = c.user_id WHERE c.adjuster_id = :id ORDER BY c.created_at DESC');
        $claims->execute(['id' => $id]);
        $claims = $claims->fetchAll();
        $claimIds  = array_map('intval', array_column($claims, 'id'));
        $policyIds = array_map('intval', array_column($claims, 'policy_id'));
        $claimantIds = array_values(array_unique(array_map(static fn($c) => (int)$c['user_id'], $claims)));
        $inIds = $claimIds ? implode(',', $claimIds) : '0';

        $payload['claims']   = array_map(static fn($c) => rowClaim($c, $slaDays), $claims);
        $payload['policies'] = fetchPolicies($conn, $user['role'], $id, $policyIds);
        $payload['documents']= array_map('rowDoc', fetchRows($conn, "SELECT * FROM claim_documents WHERE claim_id IN ($inIds) ORDER BY uploaded_at"));
        $payload['estimates']= fetchRows($conn, "SELECT * FROM garage_estimates WHERE claim_id IN ($inIds) ORDER BY created_at");
        $payload['workOrders'] = fetchRows($conn, "SELECT * FROM work_orders WHERE claim_id IN ($inIds) ORDER BY assigned_at DESC");
        $payload['notifications'] = fetchRows($conn, NOTIF_BY_USER_SQL . $id . ORDER_CREATED_DESC);

        $vignettes = [meRow($user)];
        foreach ($claimantIds as $cid) {
            $vignettes[] = ['id' => $cid, 'full_name' => null, 'phone' => null, 'claimant_phone' => null];
        }
        $payload['users'] = $vignettes;
        fillMultiple($conn, $payload['users'], $claimantIds);

        /* active garages, for the assign-a-garage control */
        $garages = $conn->query('SELECT id, full_name FROM users WHERE role = "garage" AND status = "active" ORDER BY full_name');
        $payload['garages'] = $garages->fetchAll();

    } else { /* garage */
        $orders = $conn->prepare('SELECT * FROM work_orders WHERE garage_user_id = :id ORDER BY assigned_at DESC');
        $orders->execute(['id' => $id]);
        $orders = $orders->fetchAll();
        $claimIds  = array_map('intval', array_column($orders, 'claim_id'));
        $inIds     = $claimIds ? implode(',', $claimIds) : '0';

        /* garage claims: only the vehicle/damage projection, never identity */
        $garageClaims = $claimIds
            ? $conn->query("SELECT c.id, c.claim_number, c.policy_id, c.claim_type, c.incident_date, c.incident_location, c.incident_description, c.status FROM claims c WHERE c.id IN ($inIds)")
                ->fetchAll()
            : [];
        $payload['claims']     = $garageClaims;
        $payload['policies']   = $claimIds
            ? $conn->query("SELECT id, vehicle_make, vehicle_model, vehicle_year, vehicle_plate FROM policies WHERE id IN (SELECT policy_id FROM claims WHERE id IN ($inIds))")
                ->fetchAll()
            : [];
        $payload['workOrders'] = $orders;
        $payload['estimates']  = fetchRows($conn, 'SELECT * FROM garage_estimates WHERE garage_user_id = ' . $id . ' ORDER BY created_at');
        $payload['notifications'] = fetchRows($conn, NOTIF_BY_USER_SQL . $id . ORDER_CREATED_DESC);
        $payload['users']      = [meRow($user)];
    }

    apiJson(200, $payload);

} catch (PDOException $e) {
    error_log($e->getMessage());
    apiJson(500, ['message' => 'Could not load your data right now.']);
}

/* ------------------------------------------------------------------ */

function meRow(array $user): array
{
    return [
        'id' => (int)$user['id'], 'uuid' => $user['uuid'], 'full_name' => $user['name'],
        'email' => $user['email'], 'phone' => null, 'role' => $user['role'], 'status' => $user['status'],
    ];
}

/** Shape a claims row for the front end (casts + computed SLA deadline). */
function rowClaim(array $c, int $slaDays): array
{
    return [
        'id'                  => (int)$c['id'],
        'claim_number'        => $c['claim_number'],
        'user_id'             => (int)$c['user_id'],
        'policy_id'           => (int)$c['policy_id'],
        'adjuster_id'         => $c['adjuster_id'] === null ? null : (int)$c['adjuster_id'],
        'incident_date'       => $c['incident_date'],
        'incident_location'   => $c['incident_location'],
        'incident_description'=> $c['incident_description'],
        'claim_type'          => $c['claim_type'],
        'estimated_damage'    => num($c['estimated_damage']),
        'approved_amount'     => num($c['approved_amount']),
        'police_report_ref'   => $c['police_report_ref'],
        'rejection_reason'    => $c['rejection_reason'],
        'status'              => $c['status'],
        'submitted_at'        => $c['submitted_at'],
        'reviewed_at'         => $c['reviewed_at'],
        'resolved_at'         => $c['resolved_at'],
        'created_at'          => $c['created_at'],
        'due_date'            => $c['submitted_at'] ? claimDue($c['submitted_at'], $slaDays) : null,
    ];
}

function fetchRows(PDO $conn, string $sql): array
{
    return $conn->query($sql)->fetchAll();
}

/** Shape a claim_documents row for the UI (typed flags + file presence). */
function rowDoc(array $d): array
{
    return [
        'id'           => (int)$d['id'],
        'claim_id'     => (int)$d['claim_id'],
        'uploaded_by'  => (int)$d['uploaded_by'],
        'doc_type'     => $d['doc_type'],
        'original_name'=> $d['original_name'],
        'file_size'    => num($d['file_size']),
        'mime_type'    => $d['mime_type'],
        'is_verified'  => cnt($d['is_verified']),
        'verified_by'  => $d['verified_by'] ? (int)$d['verified_by'] : null,
        'verified_at'  => $d['verified_at'],
        'uploaded_at'  => $d['uploaded_at'],
        'has_file'     => !empty($d['file_path']) ? 1 : 0,
    ];
}

function fetchPolicies(PDO $conn, string $role, int $id, array $policyIds): array
{
    if ($role === 'claimant') {
        $q = $conn->prepare('SELECT * FROM policies WHERE user_id = :id ORDER BY start_date DESC');
        $q->execute(['id' => $id]);
        return $q->fetchAll();
    }
    $in = $policyIds ? implode(',', array_map('intval', array_unique($policyIds))) : '0';
    return $conn->query("SELECT * FROM policies WHERE id IN ($in)")->fetchAll();
}

/** Fill in the vignette placeholders for referenced users in one query. */
function fillMultiple(PDO $conn, array &$vignettes, array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    $in = implode(',', $ids);
    $rows = $conn->query("SELECT id, uuid, full_name, email, phone, role, status FROM users WHERE id IN ($in)")->fetchAll();
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r;
    }
    foreach ($vignettes as &$v) {
        if (isset($byId[(int)$v['id']]) && $v['full_name'] === null) {
            $v['uuid'] = $byId[(int)$v['id']]['uuid'];
            $v['full_name'] = $byId[(int)$v['id']]['full_name'];
            $v['email'] = $byId[(int)$v['id']]['email'];
            $v['phone'] = $byId[(int)$v['id']]['phone'];
            $v['role'] = $byId[(int)$v['id']]['role'];
            $v['status'] = $byId[(int)$v['id']]['status'];
        }
    }
    unset($v);
}
