<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

class ApiDocTest extends TestCase
{
    /** @var ApiDoc */
    protected $apiDoc;

    protected function setUp(): void
    {
        $this->apiDoc = new ApiDoc();
    }

    public function testIsInstanceOfApiDoc(): void
    {
        $actual = $this->apiDoc;
        $this->assertInstanceOf(ApiDoc::class, $actual);
    }
}
