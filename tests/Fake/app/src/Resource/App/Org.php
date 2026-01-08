<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Org extends ResourceObject
{
    /**
     * Get organization
     *
     * @param string $id Organization ID
     */
    #[JsonSchema(schema: 'org.json')]
    public function onGet(string $id): ResourceObject
    {
        return $this;
    }
}
