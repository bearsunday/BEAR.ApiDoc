<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplFileInfo;

class RefTest extends TestCase
{
    public function testInvalidExternalRef(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid $ref');

        $file = new SplFileInfo(__DIR__ . '/Fake/app/var/json_schema/user.json');
        $schema = (object) ['type' => 'object'];

        new Ref('non_existent_schema.json', $file, $schema);
    }
}
