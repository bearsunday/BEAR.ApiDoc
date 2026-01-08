<?php

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;

class UnionType extends ResourceObject
{
    /**
     * Test union type parameter
     *
     * @param int|string $id ID can be int or string
     */
    public function onGet(int|string $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
