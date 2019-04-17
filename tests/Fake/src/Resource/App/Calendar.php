<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Calendar extends ResourceObject
{
    /**
     * @JsonSchema(key="calendar", schema="calendar.json")
     */
    public function onGet()
    {
    }
}
