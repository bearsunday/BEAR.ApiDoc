<?php
namespace BEAR\ApiDoc;
namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use function file_get_contents;

class DocGenTest extends TestCase
{
    public function test__invoke()
    {
        (new DocGen)('FakeVendor\FakeProject', __DIR__ . '/Fake/app', 'doc/api');
        $this->assertFileExists(__DIR__ . '/doc/api/index.html');
        $this->assertFileExists(__DIR__ . '/doc/api/rels/address.html');
        $this->assertFileExists(__DIR__ . '/doc/api/schema/address.json');
        $this->assertContains('<li><a href="rels/ticket.html">ticket</a>', file_get_contents(__DIR__ . '/doc/api/index.html'));
        $this->assertContains('<h2>/address</h2>', file_get_contents(__DIR__ . '/doc/api/rels/address.html'));
        $this->assertContains('$id": "address.json', file_get_contents(__DIR__ . '/doc/api/schema/address.json'));
    }
}
