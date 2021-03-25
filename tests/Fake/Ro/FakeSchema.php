<?php

namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class FakeSchema extends ResourceObject
{
    /**
     * @param string $id This is fake id
     *
     * @JsonSchema(schema="ticket.json", params="todo.request.json")
     */
    public function onGet(string $id): ResourceObject
    {
        unset($id);
        return $this;
    }
}
