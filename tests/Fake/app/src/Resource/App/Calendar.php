<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

class Calendar extends ResourceObject
{
    /**
     * Get schedule
     *
     * @param string $date Target date (YYYY-MM-DD format)
     */
    #[JsonSchema(schema: 'calendar.json', key: 'calendar')]
    public function onGet(string $date = 'today')
    {
    }
}
