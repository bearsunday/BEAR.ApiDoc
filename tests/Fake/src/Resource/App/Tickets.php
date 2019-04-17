<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Tickets extends ResourceObject
{
    /**
     * @JsonSchema(schema="tickets.json")
     */
    public function onGet() : ResourceObject
    {
        return $this;
    }
}
