<?php
namespace BEAR\ApiDoc;
namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use function dirname;
use function file_get_contents;
use function passthru;

class DocGenTest extends TestCase
{
    public function test__invoke()
    {
        (new DocGen)('FakeVendor\FakeProject', __DIR__ . '/doc/api');
        $this->assertFileExists(__DIR__ . '/doc/api/index.html');
        $this->assertFileExists(__DIR__ . '/doc/api/rels/person.html');
        $this->assertFileExists(__DIR__ . '/doc/api/rels/person.json');
        $this->assertFileExists(__DIR__ . '/doc/api/schema/address.json');
        $this->assertFileExists(__DIR__ . '/doc/api/schema/address.json');
        $this->assertContains('<h1>person', file_get_contents(__DIR__ . '/doc/api/rels/person.html'));
        $this->assertContains(' "rel": "person"', file_get_contents(__DIR__ . '/doc/api/rels/person.json'));
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
