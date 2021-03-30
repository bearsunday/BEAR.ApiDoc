<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function file_get_contents;
use function json_decode;

class JsonSchemaTest extends TestCase
{
    public function testNewInstance(): void
    {
        $jsonFile = __DIR__ . '/Fake/var/schema/response/ticket.json';
        $jsonSchema = new Schema(new SplFileInfo($jsonFile), json_decode((string) file_get_contents($jsonFile)), new ArrayObject());
        $this->assertInstanceOf(Schema::class, $jsonSchema);
    }
}
