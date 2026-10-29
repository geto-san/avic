<?php
declare(strict_types=1);

namespace Avic\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly string $ip
    ) {
    }

    public static function capture(): self
    {
        $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = '/' . trim($path, '/');
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            (string)($_SERVER['REMOTE_ADDR'] ?? '')
        );
    }

    /** Trimmed string input, cut to $max characters. */
    public function str(string $key, int $max = 255): string
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? '';
        return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
    }

    public function int(string $key): ?int
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? null;
        if (!is_string($v) && !is_int($v)) {
            return null;
        }
        $i = filter_var($v, FILTER_VALIDATE_INT);
        return $i === false ? null : $i;
    }

    /** A real calendar date as Y-m-d, or null. */
    public function date(string $key): ?string
    {
        $v = $this->str($key, 10);
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }

    /** Whole-shilling amount: accepts "1,500,000" or "1 500 000"; null when not a whole number. */
    public function amount(string $key): ?int
    {
        $v = preg_replace('/[\s,]/', '', $this->str($key, 20)) ?? '';
        return preg_match('/^-?\d{1,13}$/', $v) ? (int)$v : null;
    }

    /** @return array<mixed> */
    public function arr(string $key): array
    {
        $v = $this->post[$key] ?? [];
        return is_array($v) ? $v : [];
    }
}
