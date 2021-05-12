<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\ApiDoc;
use PHPUnit\Framework\TestCase;

class ApiDocTest extends TestCase
{
    public function testDumpHtml(): void
    {
        (new ApiDoc(__DIR__ . '/apidoc.html.xml'))();
        $this->assertFileExists(__DIR__ . '/docs/html/paths/address.html');
    }

    public function testDumpMd(): void
    {
        (new ApiDoc(__DIR__ . '/apidoc.md.xml'))();
        $this->assertFileExists(__DIR__ . '/docs/md/paths/address.md');
    }

    public function testDumpMarkdownWithAlpsProfile(): void
    {
        (new ApiDoc(__DIR__ . '/apidoc.alps.xml'))();
        $this->assertFileExists(__DIR__ . '/docs/html/paths/address.html');
    }
}
