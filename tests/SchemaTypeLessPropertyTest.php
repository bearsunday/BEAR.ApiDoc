<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplFileInfo;

use function file_get_contents;
use function json_decode;
use function sprintf;

class SchemaTypeLessPropertyTest extends TestCase
{
    public function testPropertiesWithoutTypeShowTheirKeyword(): void
    {
        $jsonFile = __DIR__ . '/Fake/var/schema/response/type-less.json';
        $schema = new Schema(new SplFileInfo($jsonFile), (object) json_decode((string) file_get_contents($jsonFile)), new ArrayObject());

        $types = [];
        foreach ($schema->props as $name => $prop) {
            $types[$name] = $prop->type;
        }

        $this->assertSame([
            'nameColor' => 'anyOf',
            'infoType' => 'oneOf',
            'merged' => 'allOf',
            'isOverseas' => 'enum',
            'version' => 'const',
            'anything' => '',
            'level' => 'enum',
            'levels' => 'array',
            'color' => 'string',
            'maybeCount' => 'int|null',
            'maybeCountFile' => 'int|null',
        ], $types);
    }

    public function testArrayItemsWithoutType(): void
    {
        foreach (['type-less-items.json' => '{"$ref":"#/definitions/level"}', 'type-less-enum-items.json' => '{"enum":["a","b"]}'] as $file => $constraint) {
            $jsonFile = __DIR__ . '/Fake/var/schema/response/' . $file;
            $schema = new Schema(new SplFileInfo($jsonFile), (object) json_decode((string) file_get_contents($jsonFile)), new ArrayObject());

            $this->assertStringContainsString('| enum | ' . $constraint . ' |', $schema->toStringTypeArray());
        }
    }

    /** A broken local $ref stops generation whether or not assertions run */
    #[DataProvider('brokenRefProvider')]
    public function testBrokenLocalRefStops(string $ref): void
    {
        $json = sprintf('{"type": "object", "definitions": {"color": {"type": "string"}}, "properties": {"broken": {"$ref": "%s"}}}', $ref);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid $ref ' . $ref);
        new Schema(new SplFileInfo(__FILE__), (object) json_decode($json), new ArrayObject());
    }

    /** @return array<string, array{string}> */
    public static function brokenRefProvider(): array
    {
        return [
            'missing member' => ['#/definitions/missing'],
            'not a schema' => ['#/definitions/color/type'],
        ];
    }
}
