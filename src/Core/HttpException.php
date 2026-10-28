<?php
declare(strict_types=1);

namespace Avic\Core;

use RuntimeException;

/** Stops the request with an HTTP status (404, 403, 419 ...). */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message);
    }
}
