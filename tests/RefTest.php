<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplFileInfo;

use function dirname;

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

    public function testOnlyATopLevelFileOfThePublishedDirectoryIsLinked(): void
    {
        $dir = __DIR__ . '/Fake/var/schema/response';
        $referrer = new SplFileInfo($dir . '/type-less.json');
        $property = (object) ['$ref' => 'maybe-count.json'];

        $this->assertSame('maybe-count.json', Ref::publishedSchemaFile($property, $referrer, $dir));
        $this->assertNull(Ref::publishedSchemaFile($property, $referrer, dirname($dir)));
        $this->assertNull(Ref::publishedSchemaFile((object) ['$ref' => '#/definitions/level'], $referrer, $dir));
    }
}
