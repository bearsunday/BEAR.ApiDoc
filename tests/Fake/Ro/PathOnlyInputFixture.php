<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\ResourceObject;

class PathOnlyInputFixture extends ResourceObject
{
    public function onPut(): ResourceObject
    {
        return $this;
    }
}
