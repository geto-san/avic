<?php
declare(strict_types=1);

namespace Avic\Core;

/** Field-level validation failures: errors() is [field => message]. */
final class ValidationException extends RuleViolation
{
    /** @param array<string,string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct(reset($errors) ?: 'Please check the form.');
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
