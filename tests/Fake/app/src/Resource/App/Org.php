<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Org extends ResourceObject
{
    public function onGet(): ResourceObject
    {
        return $this;
    }
}
