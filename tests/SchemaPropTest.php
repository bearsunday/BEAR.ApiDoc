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
        $schemaFile = __DIR__ . '/Fake/app/src/var/json_schema/person.json';
        $person = json_decode(file_get_contents($schemaFile));
        $age = $person->properties->age;
        $this->prop = new SchemaProp('name', 'type', true, 'desc', new SchemaConstraints($age, new SplFileInfo($schemaFile)), 'example1');
    }

    public function testNewInstance(): void
    {
        $this->assertInstanceOf(SchemaProp::class, $this->prop);
    }

    public function testToString(): void
    {
        $expected = '| name | type | desc | Optional | {"minimum":0} | example1 |';
        $this->assertSame($expected, (string) $this->prop);
    }
}
