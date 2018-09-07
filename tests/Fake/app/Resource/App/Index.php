<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;

class Index extends ResourceObject
{
    public $body = [
        'welcome' => 'Welcome to the our API.',
        'hint_1' => 'You need an account to post stuff..',
        'hint_2' => 'Create one by POSTing via the doc:ticket link..',
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
