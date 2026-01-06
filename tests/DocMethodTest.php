<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Fake\Ro\FakeNoDoc;
use BEAR\ApiDoc\Fake\Ro\FakeParamDoc;
use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFileInfo;

use function file_get_contents;
use function json_decode;

class DocMethodTest extends TestCase
{
    public function testNoPhpDoc(): void
    {
        $docMethod = new DocMethod(new AnnotationReader(), new ReflectionMethod(FakeNoDoc::class, 'onGet'), null, null, new ArrayObject(), 'md');
        $this->assertInstanceOf(DocMethod::class, $docMethod);
    }

    public function testPhpDocParamTag(): DocMethod
    {
        $requestSchemaFile = __DIR__ . '/Fake/var/schema/request/ticket.request.json';
        $responseSchemaFile = __DIR__ . '/Fake/var/schema/response/ticket.json';
        $requestSchema = new Schema(new SplFileInfo($requestSchemaFile), (object) json_decode((string) file_get_contents($requestSchemaFile)), new ArrayObject());
        $responseSchema = new Schema(new SplFileInfo($responseSchemaFile), (object) json_decode((string) file_get_contents($responseSchemaFile)), new ArrayObject());
        $docMethod = new DocMethod(new AnnotationReader(), new ReflectionMethod(FakeParamDoc::class, 'onGet'), $requestSchema, $responseSchema, new ArrayObject(), 'md');
        $this->assertInstanceOf(DocMethod::class, $docMethod);

        return $docMethod;
    }

    /**
     * @depends testPhpDocParamTag
     */
    public function testToString(DocMethod $method): void
    {
        $this->assertStringContainsString('### Request', (string) $method);
        $this->assertStringContainsString('### Response', (string) $method);
    }

    public function testArrayData(): void
    {
        $responseSchemaFile = __DIR__ . '/Fake/app/src/var/json_schema/array.json';
        $responseSchema = new Schema(new SplFileInfo($responseSchemaFile), (object) json_decode((string) file_get_contents($responseSchemaFile)), new ArrayObject());
        $docMethod = new DocMethod(new AnnotationReader(), new ReflectionMethod(FakeParamDoc::class, 'onGet'), null, $responseSchema, new ArrayObject(), 'md');
        $this->assertInstanceOf(DocMethod::class, $docMethod);
        $this->assertStringContainsString('### Request', (string) $docMethod);
        $this->assertStringContainsString('### Response', (string) $docMethod);
        $this->assertStringContainsString('[Object: Array](../schema/array.json)', (string) $docMethod);
    }

    /**
     * @requires PHP >= 999
     */
    public function testEmbed(): void
    {
        $this->markTestSkipped('Requires Embed attribute support');
    }
}
