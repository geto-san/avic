<?php
declare(strict_types=1);

use Avic\Domain\Catalog;

/** HTML-escape for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Whole Uganda shillings: 1500000 -> "UGX 1,500,000". */
function money(int|string|null $amount): string
{
    return 'UGX ' . number_format((int)$amount);
}

function fmt_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $t = strtotime($value);
    return $t ? date('d M Y', $t) : '—';
}

function fmt_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $t = strtotime($value);
    return $t ? date('d M Y, H:i', $t) : '—';
}

/** "natural_disaster" -> "Natural disaster" (catalog label when known). */
function label(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    foreach (Catalog::all() as $map) {
        if (isset($map[$value])) {
            return $map[$value];
        }
    }
    return ucfirst(str_replace('_', ' ', $value));
}

/** A coloured status pill. */
function badge(string $value, ?string $text = null): string
{
    return '<span class="badge badge-' . e($value) . '">' . e($text ?? label($value)) . '</span>';
}

function selected(mixed $a, mixed $b): string
{
    return (string)$a === (string)$b ? ' selected' : '';
}
