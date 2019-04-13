<?php
namespace BEAR\ApiDoc;
namespace BEAR\ApiDoc;

use function dirname;
use function file_get_contents;
use function passthru;
use PHPUnit\Framework\TestCase;

class DocGenTest extends TestCase
{
    public function test__invoke()
    {
        (new DocGen)('FakeVendor\FakeProject', 'tests/doc/api');
        $this->assertFileExists(__DIR__ . '/doc/api/index.html');
        $this->assertFileExists(__DIR__ . '/doc/api/rels/address.html');
        $this->assertFileExists(__DIR__ . '/doc/api/schema/address.json');
        $this->assertContains('<li><a href="rels/ticket.html">ticket</a>', file_get_contents(__DIR__ . '/doc/api/index.html'));
        $this->assertContains('<h2>/address</h2>', file_get_contents(__DIR__ . '/doc/api/rels/address.html'));
        $this->assertContains('$id": "http://example.com/schema/address.json', file_get_contents(__DIR__ . '/doc/api/schema/address.json'));
    }

    public function testApiDocCommand()
    {
        $appDir = dirname(__DIR__); // for autoloader
        $bin = $appDir . '/bin/bear.apidoc';
        $appName = "'FakeVendor\FakeProject'";
        $docDir = __DIR__ . '/api_html';
        $command = "$bin $appName $appDir $docDir";
        passthru($command, $exitCode);
        $this->assertSame(0, $exitCode);
    }
}
