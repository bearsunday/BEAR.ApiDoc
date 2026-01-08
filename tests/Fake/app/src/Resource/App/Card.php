<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Card extends ResourceObject
{
    /**
     * Get card
     *
     * @param string $id Card ID
     */
    #[JsonSchema(schema: 'card.json')]
    public function onGet(string $id): static
    {
        return $this;
    }
}
