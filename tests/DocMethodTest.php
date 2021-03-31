<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Fake\Ro\FakeNoDoc;
use BEAR\ApiDoc\Fake\Ro\FakeParamDoc;
use FakeVendor\FakeProject\Resource\App\Person;
use PHPUnit\Framework\TestCase;
use Ray\ServiceLocator\ServiceLocator;
use ReflectionMethod;
use SplFileInfo;

use function file_get_contents;
use function json_decode;

class DocMethodTest extends TestCase
{
    public function testNoPhpDoc(): void
    {
        $docMethod = new DocMethod(ServiceLocator::getReader(), new ReflectionMethod(FakeNoDoc::class, 'onGet'), null, null, new ArrayObject());
        $this->assertInstanceOf(DocMethod::class, $docMethod);
    }

    public function testPhpDocParamTag(): DocMethod
    {
        $requestSchemaFile = __DIR__ . '/Fake/var/schema/request/ticket.request.json';
        $responseSchemaFile = __DIR__ . '/Fake/var/schema/response/ticket.json';
        $requestSchema = new Schema(new SplFileInfo($requestSchemaFile), json_decode((string) file_get_contents($requestSchemaFile)), new ArrayObject());
        $responseSchema = new Schema(new SplFileInfo($responseSchemaFile), json_decode((string) file_get_contents($responseSchemaFile)), new ArrayObject());
        $docMethod = new DocMethod(ServiceLocator::getReader(), new ReflectionMethod(FakeParamDoc::class, 'onGet'), $requestSchema, $responseSchema, new ArrayObject());
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
        $responseSchema = new Schema(new SplFileInfo($responseSchemaFile), json_decode((string) file_get_contents($responseSchemaFile)), new ArrayObject());
        $docMethod = new DocMethod(ServiceLocator::getReader(), new ReflectionMethod(FakeParamDoc::class, 'onGet'), null, $responseSchema, new ArrayObject());
        $this->assertInstanceOf(DocMethod::class, $docMethod);
        $expected = <<<EOT
### Response
[Object: Array](schema/array.json)

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|-----------|---------| 
| fruits | array |  | Optional | {"items":{"type":"string"}} |  |
| vegetables | array |  | Optional | {"items":{"\$ref":"#\/definitions\/veggie"}} |  |
| juice | object |  | Optional | {"\$ref":"#\/definitions\/juice"} |  |
EOT;

        $result = (string) $docMethod;
        $this->assertStringContainsString($expected, $result);
    }

    public function testEmbed(): void
    {
        $responseSchemaFile = __DIR__ . '/Fake/app/src/var/json_schema/person.json';
        $responseSchema = new Schema(new SplFileInfo($responseSchemaFile), json_decode((string) file_get_contents($responseSchemaFile)), new ArrayObject());
        $docMethod = (string) new DocMethod(ServiceLocator::getReader(), new ReflectionMethod(Person::class, 'onGet'), null, $responseSchema, new ArrayObject());
        $expected = <<<EOT
| rel | src |
|-----|-----|
| org | [<code>/org?id={org_id}</code>](rg.md) |
EOT;
        $this->assertStringContainsString($expected, $docMethod);
    }
}
