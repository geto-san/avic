<?php
declare(strict_types=1);

namespace Avic\Controllers;

use Avic\Core\Action;
use Avic\Core\Auth;
use Avic\Core\Request;
use Avic\Services\FinancialService;

final class FinancialController
{
    public static function reserve(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        Action::run(self::url($p['id']), 'Reserve updated.', fn() =>
            FinancialService::setReserve($p['id'], (int)$r->int('exposure_id'), $r->amount('amount'), $r->str('memo', 255), $u));
    }

    public static function payment(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        Action::run(self::url($p['id']), 'Payment issued.', fn() => FinancialService::issuePayment(
            $p['id'], (int)$r->int('exposure_id'), $r->amount('amount'), $r->str('payee', 120), $r->str('method', 20), $r->str('reference', 80), $u));
    }

    public static function recovery(Request $r, array $p): void
    {
        $u = Auth::require('adjuster');
        Action::run(self::url($p['id']), 'Recovery recorded.', fn() =>
            FinancialService::recordRecovery($p['id'], (int)$r->int('exposure_id'), $r->amount('amount'), $r->str('source', 120), $u));
    }

    private static function url(int $id): string
    {
        return '/claims/' . $id . '?tab=financials';
    }
}
