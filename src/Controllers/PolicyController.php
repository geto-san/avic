<?php
declare(strict_types=1);

namespace Avic\Controllers;

use Avic\Core\Action;
use Avic\Core\Auth;
use Avic\Core\Request;
use Avic\Core\View;
use Avic\Services\LocalPolicyGateway;

final class PolicyController
{
    public static function index(Request $r, array $p): void
    {
        $u = Auth::require('claimant');
        View::render('policies/index', ['title' => 'My policies', 'policies' => (new LocalPolicyGateway())->forUser((int)$u['id'])]);
    }

    public static function link(Request $r, array $p): void
    {
        $u = Auth::require('claimant');
        Action::run('/policies', 'Policy linked to your account.', fn() =>
            (new LocalPolicyGateway())->link($r->str('policy_number', 30), $r->str('plate', 20), $u));
    }
}
