<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\ApiDoc;
use BEAR\ApiDoc\Exception\InvalidAppNamespaceException;
use PHPUnit\Framework\TestCase;

use function chdir;
use function file_get_contents;
use function json_decode;

class ApiDocTest extends TestCase
{
    public function testDumpHtml(): void
    {
        (new ApiDoc())(__DIR__ . '/apidoc.html.xml');
        $this->assertFileExists(__DIR__ . '/docs/html/paths/address.html');
    }

    public function testDumpMd(): void
    {
        (new ApiDoc())('tests/apidoc.md.xml');
        $this->assertFileExists(__DIR__ . '/docs/md/paths/address.md');
    }

    public function testDumpMarkdownWithAlpsProfile(): void
    {
        chdir(__DIR__); // /tests
        (new ApiDoc())('apidoc.alps.xml');
        $this->assertFileExists(__DIR__ . '/docs/html/paths/address.html');
    }

    public function testCurretDirectoryConfig(): void
    {
        chdir(__DIR__); // /tests
        $msg = (new ApiDoc())('');
        $this->assertStringContainsString('/base/index.html', $msg);
    }

    public function testInvalidAppName(): void
    {
        $this->expectException(InvalidAppNamespaceException::class);
        (new ApiDoc())(__DIR__ . '/apidoc.invalid.xml');
    }

    public function testDumpOpenApi(): void
    {
        $msg = (new ApiDoc())(__DIR__ . '/apidoc.openapi.xml');
        $this->assertStringContainsString('/openapi/openapi.json', $msg);
        $this->assertFileExists(__DIR__ . '/docs/openapi/openapi.json');

        $openApiJson = file_get_contents(__DIR__ . '/docs/openapi/openapi.json');
        $this->assertNotFalse($openApiJson);

        $openApi = json_decode($openApiJson, true);
        $this->assertIsArray($openApi);

        // Verify OpenAPI structure
        $this->assertArrayHasKey('openapi', $openApi);
        $this->assertSame('3.0.0', $openApi['openapi']);

        // Verify info section
        $this->assertArrayHasKey('info', $openApi);
        $this->assertArrayHasKey('title', $openApi['info']);
        $this->assertSame('API Documentation', $openApi['info']['title']);
        $this->assertArrayHasKey('description', $openApi['info']);
        $this->assertArrayHasKey('version', $openApi['info']);

        // Verify paths section
        $this->assertArrayHasKey('paths', $openApi);
        $this->assertIsArray($openApi['paths']);
        $this->assertNotEmpty($openApi['paths']);

        // Verify components/schemas section
        $this->assertArrayHasKey('components', $openApi);
        $this->assertArrayHasKey('schemas', $openApi['components']);
    }
}
