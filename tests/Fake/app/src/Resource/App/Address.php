<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

/**
 * Address
 *
 * This is the summary of Address. line 1
 * This is the summary of Address. line 2
 *
 * @link http://www.example.com/1 Link description 1
 * @link http://www.example.com/2 Link description 2
 *
 * @package FakeVendor\FakeProject\Resource\App
 */
class Address extends ResourceObject
{
    /**
     * @JsonSchema(key="address", schema="address.json")
     */
    public function onGet()
    {
    }
}
