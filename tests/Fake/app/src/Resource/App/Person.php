<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Person extends ResourceObject
{
    /**
     * @param string $id The unique ID of the person.
     *
     * @Embed(rel="org", src="/org?id={org_id}")
     * @Link(rel="card", href="/card?id={card_id}")
     * @Link(rel="tickets", href="/tickets")
     * @JsonSchema(schema="person.json")
     */
    public function onGet(string $id = 'koriym')
    {
    }
}
