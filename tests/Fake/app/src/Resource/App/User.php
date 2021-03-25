<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class User extends ResourceObject
{
    /**
     * @JsonSchema(schema="user.json")
     *
     * @param string $id      User ID
     * @param string $options User Options
     *
     * @Link(rel="person", href="/person", method="get")
     * @Link(rel="calendar", href="/calendar", method="get")
     * @Embed(rel="ticket", src="/ticket/{id}")
     */
    public function onGet(string $id, string $options = 'guest')
    {
    }

    /**
     * Create user
     *
     * Create user with given name and age
     *
     * @param string   $name The name of the user
     * @param int      $age  The age of the user
     */
    public function onPost(string $name, int $age)
    {
    }
}
