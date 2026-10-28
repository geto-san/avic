<?php
declare(strict_types=1);

namespace Avic\Controllers;

use Avic\Core\Auth;
use Avic\Core\Request;
use Avic\Core\View;
use Avic\Services\ActivityService;
use Avic\Services\ClaimService;
use Avic\Services\LocalPolicyGateway;

final class DashboardController
{
    public static function index(Request $r, array $p): void
    {
        $u = Auth::require();
        if (Auth::isAdjuster($u)) {
            View::render('dashboard/adjuster', [
                'title' => 'Dashboard',
                'stats' => ClaimService::adjusterStats((int)$u['id']),
                'activities' => ActivityService::openFor((int)$u['id'], 8),
                'unassigned' => ClaimService::search(['view' => 'unassigned'], $u, 5)['rows'],
            ]);
            return;
        }
        $claims = ClaimService::search([], $u, 10);
        View::render('dashboard/claimant', [
            'title' => 'Dashboard',
            'claims' => $claims['rows'],
            'policies' => (new LocalPolicyGateway())->forUser((int)$u['id']),
        ]);
    }
}
