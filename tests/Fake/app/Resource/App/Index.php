<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;

class Index extends ResourceObject
{
    public $body = [
        'message' => 'Welcome to the our API !

Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
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
