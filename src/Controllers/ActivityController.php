<?php
declare(strict_types=1);

namespace Avic\Controllers;

use Avic\Core\Action;
use Avic\Core\Auth;
use Avic\Core\Request;
use Avic\Core\View;
use Avic\Services\ActivityService;

final class ActivityController
{
    public static function index(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        View::render('activities/index', ['title' => 'My activities', 'activities' => ActivityService::openFor((int)$u['id'])]);
    }

    public static function create(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        $in = [
            'subject' => $r->str('subject', 150), 'description' => $r->str('description', 500),
            'priority' => $r->str('priority', 10), 'due_date' => $r->date('due_date'),
        ];
        Action::run('/claims/' . $p['id'] . '?tab=activities', 'Activity added.', fn() => ActivityService::create($p['id'], $in, $u));
    }

    public static function complete(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        $claimId = 0;
        $back = $r->str('back') === 'list' ? '/activities' : null;
        try {
            $claimId = ActivityService::complete($p['id'], $u);
            \Avic\Core\Flash::add('success', 'Activity completed.');
        } catch (\Avic\Core\RuleViolation $e) {
            \Avic\Core\Flash::add('error', $e->getMessage());
        }
        View::redirect($back ?? '/claims/' . ($claimId ?: (int)\Avic\Core\Db::val('SELECT claim_id FROM activities WHERE id = ?', [$p['id']])) . '?tab=activities');
    }

    public static function skip(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        $back = $r->str('back') === 'list' ? '/activities' : null;
        try {
            $claimId = ActivityService::skip($p['id'], $u);
            \Avic\Core\Flash::add('success', 'Activity skipped.');
        } catch (\Avic\Core\RuleViolation $e) {
            \Avic\Core\Flash::add('error', $e->getMessage());
            $claimId = (int)\Avic\Core\Db::val('SELECT claim_id FROM activities WHERE id = ?', [$p['id']]);
        }
        View::redirect($back ?? '/claims/' . $claimId . '?tab=activities');
    }
}
