<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\DocApp;
use PHPUnit\Framework\TestCase;

class DocAppTest extends TestCase
{
    public function testDumpMarkdown(): void
    {
        $docApp = new DocApp('FakeVendor\FakeProject');
        $docApp->dumpMarkDown(__DIR__ . '/markdown', 'app');
        $this->assertFileExists(__DIR__ . '/markdown/address.md');
    }

    public function testDumpMarkdownWithAlpsProfile(): void
    {
        $docApp = new DocApp('FakeVendor\FakeProject');
        $docApp->dumpMarkDown(__DIR__ . '/markdown', 'app', __DIR__ . '/Fake/app/profile.json');
        $this->assertFileExists(__DIR__ . '/markdown/address.md');
    }
}
