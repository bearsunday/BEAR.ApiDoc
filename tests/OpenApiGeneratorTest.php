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
}
