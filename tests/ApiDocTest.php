<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\ApiDoc;
use BEAR\ApiDoc\Exception\InvalidAppNamespaceException;
use PHPUnit\Framework\TestCase;

use function chdir;

class ApiDocTest extends TestCase
{
    public function testDumpHtml(): void
    {
        (new ApiDoc())(__DIR__ . '/apidoc.html.xml');
        $this->assertFileExists(__DIR__ . '/docs/html/paths/address.html');
    }

    public function testDumpMd(): void
    {
        (new ApiDoc())('tests/apidoc.md.xml');
        $this->assertFileExists(__DIR__ . '/docs/md/paths/address.md');
    }

    public function testDumpMarkdownWithAlpsProfile(): void
    {
        chdir(__DIR__); // /tests
        (new ApiDoc())('apidoc.alps.xml');
        $this->assertFileExists(__DIR__ . '/docs/html/paths/address.html');
    }

    public function testCurretDirectoryConfig(): void
    {
        chdir(__DIR__); // /tests
        $msg = (new ApiDoc())('');
        $this->assertStringContainsString('/base/index.html', $msg);
    }

    public function testInvalidAppName(): void
    {
        $this->expectException(InvalidAppNamespaceException::class);
        (new ApiDoc())(__DIR__ . '/apidoc.invalid.xml');
    }
}
