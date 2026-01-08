<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Numbers extends ResourceObject
{
    /**
     * Get numbers
     *
     * @param int $count Number of items to return
     */
    #[JsonSchema(schema: 'numbers.json')]
    public function onGet(int $count = 10): static
    {
        return $this;
    }
}
