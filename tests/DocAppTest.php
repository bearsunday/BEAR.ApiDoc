<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\DocApp;
use PHPUnit\Framework\TestCase;

class DocAppTest extends TestCase
{
    public function testInvoke(): void
    {
        $docApp = new DocApp('FakeVendor\FakeProject');
        $docApp(__DIR__ . '/docs', 'app');
    }
}
