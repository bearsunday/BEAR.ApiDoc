<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Fake\Ro;

final readonly class JsonSchemaInput
{
    public function __construct(
        public mixed $ids,
        public mixed $status = null,
        /** DTO docblock fallback description */
        public mixed $docOnly = null,
    ) {
    }
}
