<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

/** @psalm-pure */
final class TagParam
{
    public function __construct(
        public string $type,
        public string $description
    ) {
    }
}
