<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Person extends ResourceObject
{
    /**
     * @JsonSchema(schema="person.json")
     *
     * @param string $id The unique ID of the person.
     */
    public function onGet(string $id = 'koriym')
    {
    }
}
