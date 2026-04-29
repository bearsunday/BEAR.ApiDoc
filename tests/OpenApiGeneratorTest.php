<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function assert;
use function file_get_contents;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

class OpenApiGeneratorTest extends TestCase
{
    public function testGeneratedOpenApiValidatesAgainstSchema(): void
    {
        // Generate OpenAPI
        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.openapi.xml');

        // Load generated OpenAPI JSON
        $openApiJson = file_get_contents(__DIR__ . '/docs/openapi/openapi.json');
        $this->assertIsString($openApiJson);

        $openApiData = json_decode($openApiJson);
        $this->assertIsObject($openApiData);

        // Load OpenAPI 3.1 schema
        $schemaJson = file_get_contents(__DIR__ . '/schema/openapi-3.1.json');
        $this->assertIsString($schemaJson);

        $schema = json_decode($schemaJson);
        $this->assertIsObject($schema);

        // Validate
        $validator = new Validator();
        $validator->validate($openApiData, $schema);

        $errors = $validator->getErrors();
        $errorMessages = [];
        foreach ($errors as $error) {
            assert(is_array($error) && isset($error['property'], $error['message']));
            $property = is_string($error['property']) ? $error['property'] : '';
            $message = is_string($error['message']) ? $error['message'] : '';
            $errorMessages[] = sprintf('[%s] %s', $property, $message);
        }

        $this->assertTrue(
            $validator->isValid(),
            "OpenAPI validation failed:\n" . implode("\n", $errorMessages)
        );
    }

    public function testGeneratedOpenApiUsesDtoInputDocblockDescription(): void
    {
        $openApiData = $this->generateOpenApiData();
        $operation = $this->operation($openApiData, '/contact-with-descriptions', 'post');
        $properties = $this->requestBodyProperties($operation);

        $this->assertSame('Contact name for display', $properties['name']['description'] ?? null);
        $this->assertArrayHasKey('age', $properties);
        $this->assertArrayNotHasKey('description', $properties['age']);
        $this->assertArrayNotHasKey('parameters', $operation);
    }

    public function testGeneratedOpenApiEmitsDtoInputRequestBodyWithoutJsonSchema(): void
    {
        $openApiData = $this->generateOpenApiData();
        $operation = $this->operation($openApiData, '/contact', 'post');
        $schema = $this->requestBodySchema($operation);
        $properties = $this->requestBodyProperties($operation);

        $this->assertArrayNotHasKey('parameters', $operation);
        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertSame(['name', 'email', 'age', 'subject'], array_keys($properties));
        $this->assertSame(['name', 'email', 'subject'], $schema['required'] ?? null);
        $this->assertSame('string', $properties['name']['type'] ?? null);
        $this->assertSame('string', $properties['email']['type'] ?? null);
        $this->assertSame('integer', $properties['age']['type'] ?? null);
        $this->assertSame('string', $properties['subject']['type'] ?? null);
        $this->assertSame('Contact name for display', $properties['name']['description'] ?? null);
        $this->assertSame('Contact email address', $properties['email']['description'] ?? null);
    }

    /** @return array<string, mixed> */
    private function generateOpenApiData(): array
    {
        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.openapi.xml');

        $openApiJson = file_get_contents(__DIR__ . '/docs/openapi/openapi.json');
        $this->assertIsString($openApiJson);

        $openApiData = json_decode($openApiJson, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($openApiData);

        /** @var array<string, mixed> $openApiData */
        return $openApiData;
    }

    /**
     * @param array<string, mixed> $openApiData
     *
     * @return array<string, mixed>
     */
    private function operation(array $openApiData, string $path, string $method): array
    {
        $paths = $openApiData['paths'] ?? null;
        $this->assertIsArray($paths);
        $pathItem = $paths[$path] ?? null;
        $this->assertIsArray($pathItem);
        $operation = $pathItem[$method] ?? null;
        $this->assertIsArray($operation);

        /** @var array<string, mixed> $operation */
        return $operation;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private function requestBodySchema(array $operation): array
    {
        $requestBody = $operation['requestBody'] ?? null;
        $this->assertIsArray($requestBody);
        $content = $requestBody['content'] ?? null;
        $this->assertIsArray($content);
        $json = $content['application/json'] ?? null;
        $this->assertIsArray($json);
        $schema = $json['schema'] ?? null;
        $this->assertIsArray($schema);

        /** @var array<string, mixed> $schema */
        return $schema;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, array<string, mixed>>
     */
    private function requestBodyProperties(array $operation): array
    {
        $schema = $this->requestBodySchema($operation);
        $properties = $schema['properties'] ?? null;
        $this->assertIsArray($properties);

        /** @var array<string, array<string, mixed>> $properties */
        return $properties;
    }
}
