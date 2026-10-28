<?php
declare(strict_types=1);

namespace Avic\Controllers;

use Avic\Core\Action;
use Avic\Core\Auth;
use Avic\Core\Db;
use Avic\Core\HttpException;
use Avic\Core\Request;
use Avic\Core\View;
use Avic\Domain\ClaimLifecycle;
use Avic\Services\ActivityService;
use Avic\Services\AuditLog;
use Avic\Services\ClaimService;
use Avic\Services\DocumentService;
use Avic\Services\FinancialService;

final class ClaimController
{
    private const ADJUSTER_TABS = ['summary', 'exposures', 'activities', 'financials', 'documents', 'history'];
    private const CLAIMANT_TABS = ['summary', 'exposures', 'documents', 'history'];

    public static function index(Request $r, array $p): void
    {
        $u = Auth::require();
        $filters = [
            'view' => in_array($r->str('view'), ['mine', 'unassigned', 'all'], true) ? $r->str('view') : 'mine',
            'status' => $r->str('status', 20),
            'q' => $r->str('q', 60),
            'page' => $r->int('page') ?? 1,
        ];
        View::render('claims/index', ['title' => 'Claims', 'f' => $filters, 'result' => ClaimService::search($filters, $u)]);
    }

    public static function show(Request $r, array $p): void
    {
        $u = Auth::require();
        $claim = ClaimService::get($p['id'], $u);
        $adjuster = Auth::isAdjuster($u);
        $tabs = $adjuster ? self::ADJUSTER_TABS : self::CLAIMANT_TABS;
        $tab = in_array($r->str('tab'), $tabs, true) ? $r->str('tab') : 'summary';

        $vars = [
            'title' => $claim['claim_number'], 'claim' => $claim, 'tab' => $tab, 'tabs' => $tabs, 'adjuster' => $adjuster,
            'flags' => ClaimService::flags($claim),
            'exposures' => ClaimService::exposures($p['id']),
            'documents' => DocumentService::forClaim($p['id']),
            'history' => AuditLog::forClaim($p['id'], !$adjuster),
            'totals' => FinancialService::claimTotals($p['id']),
            'actions' => $adjuster ? ClaimLifecycle::targets($claim['status']) : [],
            'openActivities' => $adjuster ? (int)Db::val("SELECT COUNT(*) FROM activities WHERE claim_id = ? AND status = 'open'", [$p['id']]) : 0,
        ];
        if ($adjuster) {
            $vars += [
                'activities' => ActivityService::forClaim($p['id']),
                'exposureTotals' => FinancialService::totals($p['id']),
                'ledger' => FinancialService::ledger($p['id']),
                'adjusters' => Db::all("SELECT id, full_name, region FROM users WHERE role = 'adjuster' AND status = 'active' ORDER BY full_name"),
                'errors' => [],
            ];
        } else {
            $vars['payments'] = Db::all("SELECT amount, payment_method, created_at FROM transactions WHERE claim_id = ? AND type = 'payment' ORDER BY id DESC", [$p['id']]);
        }
        View::render('claims/show', $vars);
    }

    public static function transition(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        $to = $r->str('to', 20);
        Action::run(self::url($p['id']), 'Claim updated.', fn() =>
            ClaimService::transition($p['id'], $to, $u, $r->str('outcome', 20) ?: null));
    }

    public static function assign(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        $to = $r->str('assignee') === 'me' ? (int)$u['id'] : ($r->int('assignee') ?: null);
        Action::run(self::url($p['id']), 'Assignment updated.', fn() => ClaimService::assign($p['id'], $to, $u));
    }

    public static function addExposure(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        $x = ['type' => $r->str('type', 30), 'party' => $r->str('party', 100), 'description' => $r->str('description', 500)];
        Action::run(self::url($p['id'], 'exposures'), 'Exposure added.', fn() => ClaimService::addExposure($p['id'], $x, $u));
    }

    public static function upload(Request $r, array $p): void
    {
        $u = Auth::require();
        ClaimService::get($p['id'], $u);   // 404 unless the user may see this claim
        Action::run(self::url($p['id'], 'documents'), 'Document uploaded.', fn() =>
            DocumentService::store($p['id'], $r->str('doc_type', 30), $r->files['file'] ?? [], $u));
    }

    public static function download(Request $r, array $p): void
    {
        $u = Auth::require();
        [$doc, $path] = DocumentService::open($p['id'], $u);
        $inline = $doc['mime_type'] !== 'application/pdf' && $r->str('download') === '';
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($doc['original_name']));
        readfile($path);
        exit;
    }

    private static function url(int $id, string $tab = 'summary'): string
    {
        return '/claims/' . $id . '?tab=' . $tab;
    }
}
