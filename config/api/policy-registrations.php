<?php
declare(strict_types=1);

// Claimants register existing cover; an adjuster verifies it before creating a usable policy.
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/policy-fields.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) apiJson(405, ['message'=>'Method not allowed']);
$user = apiUser(['claimant','adjuster']);
if ($method === 'GET') {
    $query = $conn->prepare('SELECT r.*, u.full_name FROM policy_registrations r JOIN users u ON u.id = r.user_id ' .
        ($user['role'] === 'claimant' ? 'WHERE r.user_id = ? ' : '') . 'ORDER BY r.id DESC');
    $query->execute($user['role'] === 'claimant' ? [$user['id']] : []);
    apiJson(200, ['registrations'=>$query->fetchAll()]);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) apiJson(400, ['message'=>'Invalid request body']);
try {
    if ($user['role'] === 'claimant') {
        [$fields,$errors] = policyFields($input + ['user_id'=>$user['id']], false);
        $fields['user_id'] = (int)$user['id']; // Ownership always comes from the session.
        $number = trim(is_string($input['policy_number'] ?? null) ? $input['policy_number'] : '');
        $insurer = trim(is_string($input['insurer_name'] ?? null) ? $input['insurer_name'] : '');
        if (!$number || mb_strlen($number)>50) $errors['policy_number']='Enter the number printed on your policy (up to 50 characters).';
        if (!$insurer || mb_strlen($insurer)>150) $errors['insurer_name']='Enter your insurance company (up to 150 characters).';
        if ($errors) apiJson(422, ['message'=>'Correct the highlighted fields.','errors'=>$errors]);
        $result = inTransaction($conn, static function () use ($conn,$user,$input,$fields,$number,$insurer): array {
            $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$user['id']]);
            $query = $conn->prepare('SELECT id FROM policies WHERE policy_number = ?'); $query->execute([$number]);
            if ($query->fetchColumn()) throw new ApiAbort('This policy number is already registered.',409);
            $id = (int)($input['id'] ?? 0);
            if ($id) {
                $query = $conn->prepare('SELECT * FROM policy_registrations WHERE id = ? FOR UPDATE'); $query->execute([$id]); $row=$query->fetch();
                if (!$row || (int)$row['user_id'] !== (int)$user['id']) throw new ApiAbort('Registration not found.',404);
                if ($row['status'] !== 'rejected') throw new ApiAbort('Only a returned registration can be edited.',409);
                $conn->prepare('UPDATE policy_registrations SET policy_number=?, insurer_name=?, details=?, status="pending", review_notes=NULL, reviewed_by=NULL, reviewed_at=NULL WHERE id=?')->execute([$number,$insurer,json_encode($fields),$id]);
            } else {
                $conn->prepare('INSERT INTO policy_registrations (user_id,policy_number,insurer_name,details) VALUES (?,?,?,?)')->execute([$user['id'],$number,$insurer,json_encode($fields)]);
                $id=(int)$conn->lastInsertId();
            }
            audit($conn,$user,'policy.registered','policy_registration',$id,null,['policy_number'=>$number,'insurer_name'=>$insurer]);
            notify($conn,(int)$user['id'],null,'policy','Policy registration received',$number . ' is awaiting verification. It becomes available for claims after verification.');
            foreach ($conn->query('SELECT id FROM users WHERE role="adjuster" AND status="active"')->fetchAll(PDO::FETCH_COLUMN) as $adjuster) {
                notify($conn,(int)$adjuster,null,'policy','Policy verification needed',$number . ' from ' . $insurer . ' is awaiting review under Policies.');
            }
            return ['id'=>$id];
        });
        apiJson(201,['ok'=>true]+$result);
    }
    $result = inTransaction($conn, static function () use ($conn,$user,$input): array {
        $query=$conn->prepare('SELECT user_id FROM policy_registrations WHERE id=?'); $query->execute([(int)($input['id'] ?? 0)]); $owner=(int)$query->fetchColumn();
        if (!$owner) throw new ApiAbort('Registration not found.',404);
        $query=$conn->prepare('SELECT status FROM users WHERE id=? FOR UPDATE'); $query->execute([$owner]);
        if ($query->fetchColumn() !== 'active') throw new ApiAbort('The claimant account must be active.',409);
        $query=$conn->prepare('SELECT * FROM policy_registrations WHERE id=? FOR UPDATE'); $query->execute([(int)$input['id']]); $row=$query->fetch();
        if ($row['status'] !== 'pending') throw new ApiAbort('This registration has already been reviewed.',409);
        $action=$input['action'] ?? ''; $notes=workflowText($input['review_notes'] ?? '', 'the verification result or reason for returning the registration');
        $policyId=null;
        if ($action === 'verify') {
            $fields=json_decode($row['details'],true);
            $policyId=createPolicy($conn,$fields,$row['policy_number']); $status='verified';
        } elseif ($action === 'reject') $status='rejected';
        else throw new ApiAbort('Choose verify or return for correction.',422);
        $conn->prepare('UPDATE policy_registrations SET status=?, review_notes=?, reviewed_by=?, reviewed_at=NOW(), policy_id=? WHERE id=?')->execute([$status,$notes,$user['id'],$policyId,$row['id']]);
        audit($conn,$user,'policy.registration_'.$status,'policy_registration',(int)$row['id'],['status'=>'pending'],['status'=>$status,'review_notes'=>$notes,'policy_id'=>$policyId]);
        notify($conn,$owner,null,'policy',$status === 'verified' ? 'Policy verified' : 'Policy registration needs correction',$row['policy_number'] . ': ' . $notes);
        return ['status'=>$status,'policy_id'=>$policyId];
    });
    apiJson(200,['ok'=>true]+$result);
} catch (PDOException $error) {
    error_log($error->getMessage());
    apiJson($error->getCode() === '23000' ? 409 : 500, ['message'=>$error->getCode() === '23000' ? 'This policy number has already been registered.' : 'Could not save the policy registration.']);
}
