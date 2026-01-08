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
    #[JsonSchema(key: 'calendar', schema: 'calendar.json')]
    public function onGet(string $date = 'today')
    {
    }
}
