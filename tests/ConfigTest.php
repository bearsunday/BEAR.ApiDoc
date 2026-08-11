<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testEmptyContextAttributeFallsBackToApp(): void
    {
        $config = new Config(__DIR__ . '/apidoc.empty-context.xml');

        $this->assertSame('app', $config->context);
    }
}
