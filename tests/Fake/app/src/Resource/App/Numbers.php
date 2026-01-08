<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Numbers extends ResourceObject
{
    /**
     * Get numbers
     */
    #[JsonSchema(schema: 'numbers.json')]
    public function onGet()
    {
        return $this;
    }
}
