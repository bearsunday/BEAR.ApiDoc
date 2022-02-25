<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function explode;
use function file_get_contents;
use function in_array;
use function json_decode;
use function trim;

use const JSON_THROW_ON_ERROR;

class JsonSchemaTest extends TestCase
{
    public function testNewInstance(): Schema
    {
        $jsonFile = __DIR__ . '/Fake/var/schema/response/ticket.json';
        $jsonSchema = new Schema(new SplFileInfo($jsonFile), json_decode((string) file_get_contents($jsonFile)), new ArrayObject());
        $this->assertInstanceOf(Schema::class, $jsonSchema);

        return $jsonSchema;
    }

    /**
     * @depends testNewInstance
     */
    public function testPropRequired(Schema $jsonSchema): void
    {
        $filePath = $jsonSchema->file->getPath() . '/' . $jsonSchema->file->getFilename();
        $json = json_decode((string) file_get_contents($filePath, true), true, 512, JSON_THROW_ON_ERROR);

        foreach ($jsonSchema->props as $propName => $prop) {
            [, , , , $required] = explode('| ', (string) $prop);
            $expected = in_array($propName, $json['required'], true) ? 'Required' : 'Optional';
            $this->assertSame($expected, trim($required));
        }
    }
}
