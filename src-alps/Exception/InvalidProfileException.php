<?php

declare(strict_types=1);

namespace AlpsAsd\AlpsProfile\Exception;

use RuntimeException;

/**
 * Thrown when an ALPS profile cannot be read or parsed.
 *
 * A broken profile must fail loudly rather than silently producing
 * documentation with missing semantic text.
 */
final class InvalidProfileException extends RuntimeException
{
}
