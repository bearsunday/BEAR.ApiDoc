<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function implode;
use function json_decode;
use function sprintf;

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

        // Load OpenAPI 3.0 schema
        $schemaJson = file_get_contents(__DIR__ . '/schema/openapi-3.0.json');
        $this->assertIsString($schemaJson);

        $schema = json_decode($schemaJson);
        $this->assertIsObject($schema);

        // Validate
        $validator = new Validator();
        $validator->validate($openApiData, $schema);

        $errors = $validator->getErrors();
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = sprintf('[%s] %s', $error['property'], $error['message']);
        }

        $this->assertTrue(
            $validator->isValid(),
            "OpenAPI validation failed:\n" . implode("\n", $errorMessages)
        );
    }
}
