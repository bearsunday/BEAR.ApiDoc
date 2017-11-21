<?php
namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;

class Index extends ResourceObject
{
    public $body = [
        'message' => 'Welcome to the our API !',
        '_links' => [
            'self' => [
                'href' => '/',
            ],
            'curies' => [
                'name' => 'doc',
                'href' => 'http://apidoc.example.com/rels/{?rel}',
                'templated' => true
            ],
            'doc:user' => [
                'href' => '/user',
                'templated' => false
            ],
        ]
    ];

    public function onGet()
    {
        return $this;
    }
}
