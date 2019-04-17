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
     * @Link(rel="profile", href="/profile", method="get")
     * @Link(rel="setting", href="/setting", method="get")
     * @Embed(rel="blog", src="/blog/{id}")
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
