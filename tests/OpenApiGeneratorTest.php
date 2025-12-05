<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject;

use BEAR\ApiDoc\ApiDoc;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_exists;
use function file_get_contents;
use function is_array;
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
        assert(is_array($info));

        $this->assertArrayHasKey('title', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertSame('1.0.0', $info['version']);
    }

    public function testPathsSection(): void
    {
        $this->assertArrayHasKey('paths', $this->openApi);
        $paths = $this->openApi['paths'];
        $this->assertIsArray($paths);
        $this->assertNotEmpty($paths);

        // Check that paths have HTTP methods
        foreach ($paths as $path => $operations) {
            $this->assertIsString($path);
            $this->assertIsArray($operations);
        }
    }

    public function testComponentsSchemasSection(): void
    {
        $this->assertArrayHasKey('components', $this->openApi);
        $components = $this->openApi['components'];
        assert(is_array($components));
        $this->assertArrayHasKey('schemas', $components);
        $this->assertIsArray($components['schemas']);
    }

    public function testOperationStructure(): void
    {
        // Get first path with operations
        $firstPath = null;
        $paths = $this->openApi['paths'];
        assert(is_array($paths));

        foreach ($paths as $path => $operations) {
            if (! empty($operations) && is_array($operations)) {
                $firstPath = $operations;
                break;
            }
        }

        $this->assertNotNull($firstPath);
        assert(is_array($firstPath));

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

    public function testValidOpenApiSpec(): void
    {
        // Validate against OpenAPI 3.0 specification
        $validator = new Validator();
        $openApiJson = json_encode($this->openApi);
        assert($openApiJson !== false);
        $openApiObj = json_decode($openApiJson);
        assert($openApiObj instanceof \stdClass);

        // Basic OpenAPI 3.0 schema validation
        // Required fields at root level
        $this->assertObjectHasProperty('openapi', $openApiObj);
        $this->assertObjectHasProperty('info', $openApiObj);
        $this->assertObjectHasProperty('paths', $openApiObj);

        // Info object required fields
        assert(isset($openApiObj->info) && $openApiObj->info instanceof \stdClass);
        $this->assertObjectHasProperty('title', $openApiObj->info);
        $this->assertObjectHasProperty('version', $openApiObj->info);

        // Validate paths structure
        assert(isset($openApiObj->paths) && $openApiObj->paths instanceof \stdClass);
        $this->assertIsObject($openApiObj->paths);

        foreach ((array) $openApiObj->paths as $pathItem) {
            $this->assertIsObject($pathItem);

            // Each operation should have responses
            foreach ((array) $pathItem as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    $this->assertIsObject($operation);
                    $this->assertObjectHasProperty('responses', $operation);
                }
            }
        }
    }

    public function testParametersAreValid(): void
    {
        $paths = $this->openApi['paths'];
        assert(is_array($paths));

        foreach ($paths as $path => $operations) {
            assert(is_array($operations));
            foreach ($operations as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                // If parameters exist, validate them
                if (isset($operation['parameters'])) {
                    $this->assertIsArray($operation['parameters']);

                    foreach ($operation['parameters'] as $param) {
                        // Required parameter fields
                        $this->assertArrayHasKey('name', $param);
                        $this->assertArrayHasKey('in', $param);

                        // 'in' must be one of: query, header, path, cookie
                        $this->assertContains($param['in'], ['query', 'header', 'path', 'cookie']);

                        // If required is present, it must be boolean
                        if (isset($param['required'])) {
                            $this->assertIsBool($param['required']);
                        }
                    }
                }
            }
        }
    }

    public function testResponsesAreValid(): void
    {
        $paths = $this->openApi['paths'];
        assert(is_array($paths));

        foreach ($paths as $path => $operations) {
            assert(is_array($operations));
            foreach ($operations as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                // Every operation must have responses
                $this->assertArrayHasKey('responses', $operation);
                $this->assertIsArray($operation['responses']);
                $this->assertNotEmpty($operation['responses']);

                // Validate each response
                foreach ($operation['responses'] as $statusCode => $response) {
                    // Status code should be a string (can be "200", "default", etc.)
                    $this->assertIsString($statusCode);
                    $this->assertIsArray($response);

                    // Each response should have a description
                    $this->assertArrayHasKey('description', $response);
                }
            }
        }
    }
}
