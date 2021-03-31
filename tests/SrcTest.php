<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

class SrcTest extends TestCase
{
    public function testSrc(): void
    {
        $this->assertSame('[<code>/org{?id}</code>](org.md)', (string) new Src('/org{?id}', 'md'));
    }
}
