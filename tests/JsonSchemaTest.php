<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function array_key_exists;
use function assert;
use function explode;
use function file_get_contents;
use function in_array;
use function is_array;
use function json_decode;
use function trim;

use const JSON_THROW_ON_ERROR;

class JsonSchemaTest extends TestCase
{
    public function testNewInstance(): Schema
    {
        $jsonFile = __DIR__ . '/Fake/var/schema/response/ticket.json';
        $jsonSchema = new Schema(new SplFileInfo($jsonFile), (object) json_decode((string) file_get_contents($jsonFile)), new ArrayObject());
        $this->assertInstanceOf(Schema::class, $jsonSchema);

        return $jsonSchema;
    }

    #[Depends('testNewInstance')]
    public function testPropRequired(Schema $jsonSchema): void
    {
        $filePath = $jsonSchema->file->getPath() . '/' . $jsonSchema->file->getFilename();
        $json = (array) json_decode((string) file_get_contents($filePath, true), true, 512, JSON_THROW_ON_ERROR);

        foreach ($jsonSchema->props as $propName => $prop) {
            [, , , , $required] = explode('| ', (string) $prop);
            assert(array_key_exists('required', $json) && is_array($json['required']));
            $expected = in_array($propName, $json['required'], true) ? 'Required' : 'Optional';
            $this->assertSame($expected, trim($required));
        }
    }

    public function testArraySchemaWithRef(): void
    {
        $jsonFile = __DIR__ . '/Fake/app/docs/base/schema/tickets.json';
        $jsonSchema = new Schema(
            new SplFileInfo($jsonFile),
            (object) json_decode((string) file_get_contents($jsonFile)),
            new ArrayObject()
        );

        $this->assertSame('array', $jsonSchema->type);
        $this->assertSame('Collection of Tickets', $jsonSchema->title);

        // Test toStringTypeArray
        $output = $jsonSchema->toStringTypeArray();
        $this->assertStringContainsString('Item Type', $output);
        $this->assertStringContainsString('Constraints', $output);
    }

    public function testSchemaWithSemanticDictionary(): void
    {
        /** @var ArrayObject<string, string> $semanticDictionary */
        $semanticDictionary = new ArrayObject([
            'firstName' => 'First Name by ALPS',
            'familyName' => 'Family Name by ALPS',
        ]);

        $jsonFile = __DIR__ . '/Fake/app/docs/base/schema/person.json';
        $jsonSchema = new Schema(
            new SplFileInfo($jsonFile),
            (object) json_decode((string) file_get_contents($jsonFile)),
            $semanticDictionary
        );

        $this->assertSame('Person', $jsonSchema->title);
        $this->assertNotEmpty($jsonSchema->props);
    }

    public function testSchemaTitle(): void
    {
        $jsonFile = __DIR__ . '/Fake/app/docs/base/schema/ticket.json';
        $jsonSchema = new Schema(
            new SplFileInfo($jsonFile),
            (object) json_decode((string) file_get_contents($jsonFile)),
            new ArrayObject()
        );

        $title = $jsonSchema->title();
        $this->assertStringContainsString('Object', $title);
        $this->assertStringContainsString('Ticket', $title);
        $this->assertStringContainsString('../schemas/', $title);
    }

    public function testSchemaWithExamples(): void
    {
        $jsonFile = __DIR__ . '/Fake/app/docs/base/schema/ticket.json';
        // Create schema object with examples
        $schemaData = (object) [
            'type' => 'object',
            'title' => 'Test',
            'examples' => [(object) ['id' => 'TKT-001', 'title' => 'Test']],
        ];

        $jsonSchema = new Schema(
            new SplFileInfo($jsonFile),
            $schemaData,
            new ArrayObject()
        );

        $this->assertNotEmpty($jsonSchema->examples);
        $this->assertStringContainsString('TKT-001', $jsonSchema->examples[0]);
    }

    public function testSchemaPropertyWithExample(): void
    {
        $jsonFile = __DIR__ . '/Fake/app/docs/base/schema/ticket.json';
        $jsonSchema = new Schema(
            new SplFileInfo($jsonFile),
            (object) json_decode((string) file_get_contents($jsonFile)),
            new ArrayObject()
        );

        // ticket.json has example properties
        $this->assertArrayHasKey('id', $jsonSchema->props);
    }

    public function testSchemaWithRef(): void
    {
        // Test schema with $ref property
        $jsonFile = __DIR__ . '/Fake/app/docs/base/schema/array.json';
        $jsonSchema = new Schema(
            new SplFileInfo($jsonFile),
            (object) json_decode((string) file_get_contents($jsonFile)),
            new ArrayObject()
        );

        $this->assertSame('object', $jsonSchema->type);
        // juice property uses $ref
        $this->assertArrayHasKey('juice', $jsonSchema->props);
    }
}
