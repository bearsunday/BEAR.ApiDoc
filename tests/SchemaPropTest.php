<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function file_get_contents;
use function json_decode;

class SchemaPropTest extends TestCase
{
    /** @var SchemaProp */
    private $prop;

    protected function setUp(): void
    {
        $file = __DIR__ . '/Fake/app/src/var/json_schema/person.json';
        $age = (object) json_decode((string) file_get_contents($file))->properties->age;

        $this->prop = new SchemaProp('name', 'type', true, 'desc', new SchemaConstraints($age, new SplFileInfo($file)), '');
    }

    public function testNewInstance(): void
    {
        $this->assertInstanceOf(SchemaProp::class, $this->prop);
    }

    public function testToString(): void
    {
        $expected = '| name | type | desc | Optional | {"minimum":0} |  |';
        $this->assertSame($expected, (string) $this->prop);
    }
}
