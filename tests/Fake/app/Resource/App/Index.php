<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;

class Index extends ResourceObject
{
    public $body = [
        'message' => 'Welcome to the our API !
<ul>
    <li>more info1 <a href="http://www.example.com/more-info1">http://www.example.com/more-info1</a>
    <li>more info2 <a href="http://www.example.com/more-info1">http://www.example.com/more-info1</a>
</ul>',
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
                'href' => '/user',
                'title' => 'User'
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
