<?php
declare(strict_types=1);

namespace Avic\Domain;

/** Reads config/rules.php and answers "what should happen?" questions. */
final class Rules
{
    private static ?array $config = null;

    public static function config(): array
    {
        return self::$config ??= require AVIC_ROOT . '/config/rules.php';
    }

    /**
     * Activity templates that fire for an event.
     *
     * @param array{loss_type?:string,exposure_type?:string,flagged?:bool} $ctx
     * @return list<array<string,mixed>>
     */
    public static function activitiesFor(string $event, array $ctx): array
    {
        $out = [];
        foreach (self::config()['activities'] as $tpl) {
            if ($tpl['event'] !== $event) {
                continue;
            }
            $ok = true;
            foreach ($tpl['when'] ?? [] as $key => $want) {
                $have = $ctx[$key] ?? null;
                $ok = $ok && (is_bool($want) ? (bool)$have === $want : in_array($have, (array)$want, true));
            }
            if ($ok) {
                $out[] = $tpl;
            }
        }
        return $out;
    }

    /** @return array<string,mixed> the on_complete instruction for a template key, or [] */
    public static function onComplete(?string $key): array
    {
        foreach (self::config()['activities'] as $tpl) {
            if ($tpl['key'] === $key) {
                return $tpl['on_complete'] ?? [];
            }
        }
        return [];
    }
}
