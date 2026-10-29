<?php
declare(strict_types=1);

namespace Avic\Core;

use RuntimeException;

/** A business rule said no. The message is safe to show to the user. */
class RuleViolation extends RuntimeException
{
}
