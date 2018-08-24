<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class User extends ResourceObject
{
    /**
     * @JsonSchema(schema="user.json")
     */
    public function onGet(int $age) {
        return $this;
    }
}
