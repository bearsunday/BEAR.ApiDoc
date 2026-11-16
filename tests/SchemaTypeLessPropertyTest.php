<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function file_get_contents;
use function json_decode;

class SchemaTypeLessPropertyTest extends TestCase
{
    public function testPropertiesWithoutTypeHaveABlankType(): void
    {
        $jsonFile = __DIR__ . '/Fake/var/schema/response/type-less.json';
        $schema = new Schema(new SplFileInfo($jsonFile), (object) json_decode((string) file_get_contents($jsonFile)), new ArrayObject());

        $types = [];
        foreach ($schema->props as $name => $prop) {
            $types[$name] = $prop->type;
        }

        $this->assertSame([
            'nameColor' => '',
            'infoType' => '',
            'merged' => '',
            'isOverseas' => '',
            'version' => '',
            'anything' => '',
            'level' => '',
            'levels' => 'array',
            'color' => 'string',
        ], $types);
    }
}
