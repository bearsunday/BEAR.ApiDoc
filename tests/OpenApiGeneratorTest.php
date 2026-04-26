<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

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
        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.openapi.xml');

        $openApiJson = file_get_contents(__DIR__ . '/docs/openapi/openapi.json');
        $this->assertIsString($openApiJson);

        /** @var array{paths: array<string, array<string, array{parameters?: list<array<string, mixed>>}>>} $openApiData */
        $openApiData = json_decode($openApiJson, true, 512, JSON_THROW_ON_ERROR);
        $parameters = $openApiData['paths']['/contact-with-descriptions']['post']['parameters'] ?? [];
        $this->assertNotSame([], $parameters);

        $indexedParams = [];
        foreach ($parameters as $parameter) {
            $name = $parameter['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }

            $indexedParams[$name] = $parameter;
        }

        $this->assertSame('Contact name for display', $indexedParams['name']['description'] ?? null);
        $this->assertArrayHasKey('age', $indexedParams);
        $this->assertArrayNotHasKey('description', $indexedParams['age']);
    }
}
