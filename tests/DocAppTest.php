<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\DocApp;
use PHPUnit\Framework\TestCase;

use function dirname;

class DocAppTest extends TestCase
{
    /** @var DocApp */
    protected $docApp;

    protected function setUp(): void
    {
        $this->docApp = new DocApp('FakeVendor\FakeProject');
    }

    public function testDumpMarkdown(): void
    {
        $this->docApp->dumpMd(__DIR__ . '/md', 'app');
        $this->assertFileExists(__DIR__ . '/md/address.md');
    }

    public function testDumpMarkdownWithAlpsProfile(): void
    {
        $this->docApp->dumpMd(__DIR__ . '/md', 'app', __DIR__ . '/Fake/app/profile.json');
        $this->assertFileExists(__DIR__ . '/md/address.md');
    }

    public function testDumpHtml(): void
    {
        $this->docApp->dumpHtml(dirname(__DIR__) . '/docs', 'app');
        $this->assertFileExists(dirname(__DIR__) . '/docs/address.html');
    }
}
