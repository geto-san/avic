<?php
declare(strict_types=1);

namespace Avic\Controllers;

use Avic\Core\Auth;
use Avic\Core\Flash;
use Avic\Core\Request;
use Avic\Core\ValidationException;
use Avic\Core\View;
use Avic\Services\ClaimService;
use Avic\Services\LocalPolicyGateway;

/** First notice of loss: 1 find the policy, 2 enter the loss, 3 review and submit. */
final class FnolController
{
    public static function search(Request $r, array $p): void
    {
        Auth::require();
        unset($_SESSION['fnol']);
        View::render('fnol/search', ['title' => 'Report a loss', 'number' => '', 'error' => null]);
    }

    public static function find(Request $r, array $p): void
    {
        $u = Auth::require();
        $number = $r->str('policy_number', 30);
        $policy = (new LocalPolicyGateway())->findForActor($number, $u);
        if ($policy === null) {
            View::render('fnol/search', ['title' => 'Report a loss', 'number' => $number, 'error' => 'No policy found with that number on your account.'], 404);
            return;
        }
        $_SESSION['fnol'] = ['policy_id' => (int)$policy['id'], 'data' => []];
        View::redirect('/fnol/details');
    }

    public static function showDetails(Request $r, array $p): void
    {
        [$u, $policy] = self::context();
        $d = $_SESSION['fnol']['data'] ?? [];
        View::render('fnol/details', ['title' => 'Report a loss', 'policy' => $policy, 'old' => $d + ['exposures' => []], 'errors' => []]);
    }

    public static function saveDetails(Request $r, array $p): void
    {
        [$u, $policy] = self::context();
        $data = self::collect($r);
        $errors = ClaimService::validateFnol($policy, $data);
        if ($errors) {
            View::render('fnol/details', ['title' => 'Report a loss', 'policy' => $policy, 'old' => $data, 'errors' => $errors], 422);
            return;
        }
        $_SESSION['fnol']['data'] = $data;
        View::redirect('/fnol/review');
    }

    public static function review(Request $r, array $p): void
    {
        [$u, $policy] = self::context();
        if (empty($_SESSION['fnol']['data'])) {
            View::redirect('/fnol/details');
        }
        View::render('fnol/review', ['title' => 'Review and submit', 'policy' => $policy, 'data' => $_SESSION['fnol']['data']]);
    }

    public static function submit(Request $r, array $p): void
    {
        [$u, $policy] = self::context();
        $data = $_SESSION['fnol']['data'] ?? null;
        if (!$data) {
            View::redirect('/fnol/details');
        }
        try {
            $id = ClaimService::fnol($policy, $data, $u);
        } catch (ValidationException $e) {
            View::render('fnol/details', ['title' => 'Report a loss', 'policy' => $policy, 'old' => $data, 'errors' => $e->errors()], 422);
            return;
        }
        unset($_SESSION['fnol']);
        Flash::add('success', 'Claim submitted. Reference ' . ClaimService::get($id, $u)['claim_number'] . '.');
        View::redirect('/claims/' . $id);
    }

    /** @return array{0:array,1:array} the signed-in user and the policy chosen in step 1 (re-checked) */
    private static function context(): array
    {
        $u = Auth::require();
        $policy = isset($_SESSION['fnol']['policy_id']) ? (new LocalPolicyGateway())->find((int)$_SESSION['fnol']['policy_id']) : null;
        if ($policy === null || ($u['role'] === 'claimant' && (int)$policy['user_id'] !== (int)$u['id'])) {
            unset($_SESSION['fnol']);
            View::redirect('/fnol');
        }
        return [$u, $policy];
    }

    private static function collect(Request $r): array
    {
        $exposures = [];
        foreach ($r->arr('exposures') as $row) {
            if (!is_array($row)) {
                continue;
            }
            $x = [
                'type' => is_string($row['type'] ?? null) ? $row['type'] : '',
                'party' => mb_substr(trim((string)($row['party'] ?? '')), 0, 100),
                'description' => mb_substr(trim((string)($row['description'] ?? '')), 0, 500),
            ];
            if ($x['type'] !== '' || $x['party'] !== '' || $x['description'] !== '') {
                $exposures[] = $x;
            }
        }
        return [
            'loss_date' => $r->date('loss_date'),
            'loss_type' => $r->str('loss_type', 30),
            'loss_region' => $r->str('loss_region', 20),
            'loss_location' => $r->str('loss_location'),
            'description' => $r->str('description', 2000),
            'police_ref' => $r->str('police_ref', 60),
            'exposures' => $exposures,
        ];
    }
}
