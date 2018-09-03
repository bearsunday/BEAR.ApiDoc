<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Person extends ResourceObject
{
    /**
     * @JsonSchema(key="parson", schema="parson.json")
     */
    public function onGet()
    {
    }
}
