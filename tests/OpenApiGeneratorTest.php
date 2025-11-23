<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\ApiDoc;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_get_contents;
use function json_decode;

class OpenApiGeneratorTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $openApi;

    protected function setUp(): void
    {
        // Generate OpenAPI spec if not exists
        $openApiFile = __DIR__ . '/docs/openapi/openapi.json';
        if (! file_exists($openApiFile)) {
            (new ApiDoc())(__DIR__ . '/apidoc.openapi.xml');
        }

        $openApiJson = file_get_contents($openApiFile);
        $this->assertNotFalse($openApiJson);

        $openApi = json_decode($openApiJson, true);
        $this->assertIsArray($openApi);
        $this->openApi = $openApi;
    }

    public function testOpenApiVersion(): void
    {
        $this->assertArrayHasKey('openapi', $this->openApi);
        $this->assertSame('3.0.0', $this->openApi['openapi']);
    }

    public function testInfoSection(): void
    {
        $this->assertArrayHasKey('info', $this->openApi);
        $info = $this->openApi['info'];

        $this->assertArrayHasKey('title', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertSame('1.0.0', $info['version']);
    }

    public function testPathsSection(): void
    {
        $this->assertArrayHasKey('paths', $this->openApi);
        $this->assertIsArray($this->openApi['paths']);
        $this->assertNotEmpty($this->openApi['paths']);

        // Check that paths have HTTP methods
        foreach ($this->openApi['paths'] as $path => $operations) {
            $this->assertIsString($path);
            $this->assertIsArray($operations);
        }
    }

    public function testComponentsSchemasSection(): void
    {
        $this->assertArrayHasKey('components', $this->openApi);
        $this->assertArrayHasKey('schemas', $this->openApi['components']);
        $this->assertIsArray($this->openApi['components']['schemas']);
    }

    public function testOperationStructure(): void
    {
        // Get first path with operations
        $firstPath = null;
        foreach ($this->openApi['paths'] as $path => $operations) {
            if (! empty($operations)) {
                $firstPath = $operations;
                break;
            }
        }

        $this->assertNotNull($firstPath);

        // Get first operation
        $firstOperation = null;
        foreach ($firstPath as $method => $operation) {
            if (is_array($operation)) {
                $firstOperation = $operation;
                break;
            }
        }

        if ($firstOperation !== null) {
            $this->assertArrayHasKey('summary', $firstOperation);
            $this->assertArrayHasKey('description', $firstOperation);
            $this->assertArrayHasKey('responses', $firstOperation);
        }
    }
}
