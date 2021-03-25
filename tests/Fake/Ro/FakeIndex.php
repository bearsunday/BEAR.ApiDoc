<?php

namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\ResourceObject;

class FakeIndex extends ResourceObject
{
    public function onGet(string $id): ResourceObject
    {
        unset($id);
        return $this;
    }
}
