<?php
namespace BEAR\ApiDoc;
namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

class DocGenTest extends TestCase
{
    public function test__invoke()
    {
        (new DocGen)('FakeVendor\FakeProject', __DIR__ . '/Fake/app', 'doc/api');
    }
}
