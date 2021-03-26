<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Fake\Ro\FakeNoDoc;
use BEAR\ApiDoc\Fake\Ro\FakeParamDoc;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFileInfo;

use function file_get_contents;
use function json_decode;

class DocMethodTest extends TestCase
{
    public function testNoPhpDoc(): void
    {
        $docMethod = new DocMethod(new ReflectionMethod(FakeNoDoc::class, 'onGet'), null, null);
        $this->assertInstanceOf(DocMethod::class, $docMethod);
    }

    public function testPhpDocParamTag(): DocMethod
    {
        $requestSchemaFile = __DIR__ . '/Fake/var/schema/request/ticket.request.json';
        $responseSchemaFile = __DIR__ . '/Fake/var/schema/response/ticket.json';
        $requestSchema = new Schema(new SplFileInfo($requestSchemaFile), json_decode((string) file_get_contents($requestSchemaFile)));
        $responseSchema = new Schema(new SplFileInfo($responseSchemaFile), json_decode((string) file_get_contents($responseSchemaFile)));
        $docMethod = new DocMethod(new ReflectionMethod(FakeParamDoc::class, 'onGet'), $requestSchema, $responseSchema);
        $this->assertInstanceOf(DocMethod::class, $docMethod);

        return $docMethod;
    }

    /**
     * @depends testPhpDocParamTag
     */
    public function testToString(DocMethod $method): void
    {
        $this->assertStringContainsString('## Request', (string) $method);
        $this->assertStringContainsString('## Response', (string) $method);
    }

    public function testArrayData(): void
    {
        $responseSchemaFile = __DIR__ . '/Fake/app/src/var/json_schema/array.json';
        $responseSchema = new Schema(new SplFileInfo($responseSchemaFile), json_decode((string) file_get_contents($responseSchemaFile)));
        $docMethod = new DocMethod(new ReflectionMethod(FakeParamDoc::class, 'onGet'), null, $responseSchema);
        $this->assertInstanceOf(DocMethod::class, $docMethod);
        $expected = <<<EOT
## GET

### Request
| Name  | Type  | Description | Default | Example |
|-------|-------|-------------|---------|---------| 
| id | string | This is fake id |  |  |
        

### Response
[Object: Array](schema/array.json)

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|------------|---------| 
| fruits | array |  | Optional | {"items":{"type":"string"}} |  |
| vegetables | array |  | Optional | {"items":{"\$ref":"#\/definitions\/veggie"}} |  |
| juice | object |  | Optional | {"\$ref":"#\/definitions\/juice"}
EOT;

        $this->assertStringContainsString($expected, (string) $docMethod);
    }
}
