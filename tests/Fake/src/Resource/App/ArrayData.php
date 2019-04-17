<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class ArrayData extends ResourceObject
{
    /**
     * @JsonSchema(key="array", schema="array.json")
     */
    public function onGet()
    {
    }
}
