<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use PHPUnit\Framework\TestCase;

/** @requires PHP >= 999 */
class ApiDocTest extends TestCase
{
    public function testSkipped(): void
    {
        $this->markTestSkipped('Requires Attribute support');
    }
}
