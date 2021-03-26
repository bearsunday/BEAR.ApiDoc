<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use function file_put_contents;

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

    public function transfer(TransferInterface $responder, array $server)
    {
       file_put_contents();
    }

    public function __toString()
    {

    }
}
