<?php
declare(strict_types=1);

namespace Avic\Core;

final class Action
{
    /**
     * Run a state-changing action, flash the outcome, and redirect. A rule violation is shown
     * to the user as an error; anything else (including HttpException) is left to the router.
     */
    public static function run(string $redirect, string $success, callable $fn): never
    {
        try {
            $fn();
            Flash::add('success', $success);
        } catch (RuleViolation $e) {
            Flash::add('error', $e->getMessage());
        }
        View::redirect($redirect);
    }
}
