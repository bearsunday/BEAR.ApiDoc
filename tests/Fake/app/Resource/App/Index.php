<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;

class Index extends ResourceObject
{
    public $body = [
        'Overview' => 'This is ApiDoc REST API.
You can put "\n" in the text.".',
        'issue' => 'https://github.com/bearsunday/BEAR.ApiDoc/issues',
        '_links' => [
            'self' => [
                'href' => '/',
            ],
            'curies' => [
                'name' => 'doc',
                'href' => 'rels/{rel}.html',
                'templated' => true
            ],
            'doc:ticket' => [
                'href' => '/ticket',
                'title' => 'Tickets item',
            ],
            'doc:tickets' => [
                'href' => '/tickets',
                'title' => 'Ticket list'
            ],
            'doc:user' => [
                'href' => '/users/{id}',
                'title' => 'User',
                'templated' => true
            ],
            'doc:address' => [
                'href' => '/address',
                'title' => 'Address'
            ],
            'doc:array' => [
                'href' => '/array-data',
                'title' => 'Array'
            ],
            'doc:person' => [
                'href' => '/person',
                'title' => 'Person'
            ],
            'doc:calendar' => [
                'href' => '/calendar',
                'title' => 'Calendar'
            ]
        ]
    ];

    public function onGet()
    {
        return $this;
    }
}
