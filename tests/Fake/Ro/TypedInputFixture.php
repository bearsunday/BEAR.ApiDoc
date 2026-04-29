<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\ResourceObject;

class TypedInputFixture extends ResourceObject
{
    public function onGet(float $ratio, bool $enabled): ResourceObject
    {
        unset($ratio, $enabled);

        return $this;
    }
}
