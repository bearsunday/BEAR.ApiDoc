<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

class DocAppTest extends TestCase
{
    public function testInvoke(): void
    {
        $docApp = new DocApp('FakeVendor\FakeProject');
        $docApp(__DIR__ . '/docs', 'app');
        $this->assertFileExists(__DIR__ . '/docs/address.md');
    }
}
