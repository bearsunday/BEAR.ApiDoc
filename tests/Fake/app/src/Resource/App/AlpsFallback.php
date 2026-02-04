<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;

/**
 * Resource to test ALPS semantic dictionary fallback for descriptions
 */
class AlpsFallback extends ResourceObject
{
    /**
     * Test ALPS fallback - parameters have no PHPDoc description
     */
    public function onGet(
        string $firstName,
        string $familyName,
        int $age,
        string $foo,
    ): static {
        return $this;
    }
}
