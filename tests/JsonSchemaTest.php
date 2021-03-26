<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function file_get_contents;
use function json_decode;

class JsonSchemaTest extends TestCase
{
    public function testNewInstance(): void
    {
        $jsonFile = __DIR__ . '/Fake/var/schema/response/ticket.json';
        $jsonObject = json_decode((string) file_get_contents($jsonFile));
        $jsonSchema = new Schema(new SplFileInfo($jsonFile), $jsonObject);
        $this->assertInstanceOf(Schema::class, $jsonSchema);
    }
}
