<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Card extends ResourceObject
{
    /**
     * Get card
     */
    #[JsonSchema(schema: 'card.json')]
    public function onGet()
    {
        return $this;
    }
}
