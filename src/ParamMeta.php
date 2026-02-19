<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

/**
 * Parameter metadata extracted from OPTIONS response
 */
final readonly class ParamMeta
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $isOptional,
        public string $default,
    ) {
    }
}
