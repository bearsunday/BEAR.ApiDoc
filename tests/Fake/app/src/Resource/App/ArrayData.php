<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class ArrayData extends ResourceObject
{
    /**
     * Get array data
     *
     * @param array $items List of item IDs to retrieve
     */
    #[JsonSchema(schema: 'array.json', key: 'array')]
    public function onGet(array $items = [1, 2])
    {
    }
}
