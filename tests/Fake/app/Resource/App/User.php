<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class User extends ResourceObject
{
    /**
     * @JsonSchema(schema="user.json")
     */
    public function onGet(int $age)
    {
    }

    /**
     * Create user
     *
     * Create user with given name and age
     *
     * @param string   $name user name
     * @param int|null $age  user age
     */
    public function onPost(string $name, ?int $age)
    {
    }
}
