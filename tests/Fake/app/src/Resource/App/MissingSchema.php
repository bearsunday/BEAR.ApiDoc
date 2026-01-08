<?php

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

/**
 * Resource with missing schema file
 */
class MissingSchema extends ResourceObject
{
    /**
     * Get data with non-existent schema
     */
    #[JsonSchema(schema: 'non_existent_schema.json')]
    public function onGet(): static
    {
        return $this;
    }
}
