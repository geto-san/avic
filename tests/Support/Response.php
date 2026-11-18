<?php
declare(strict_types=1);

namespace Avic\Tests\Support;

final class Response
{
    /** @param array<string,string> $headers lower-cased name => value */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    /** @return array<string,mixed> decoded JSON body, or [] if it is not JSON */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : [];
    }

    public function message(): string
    {
        return (string)($this->json()['message'] ?? '');
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
