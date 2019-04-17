<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Address extends ResourceObject
{
    /**
     * @JsonSchema(key="address", schema="address.json")
     */
    public function onGet()
    {
    }
}
