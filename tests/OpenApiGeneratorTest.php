<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function assert;
use function file_get_contents;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function sprintf;
use function unlink;

use const JSON_THROW_ON_ERROR;

class OpenApiGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Remove derived example artifacts from previous runs so stale payloads
        // can never satisfy assertions on regenerated content.
        $examplesDir = __DIR__ . '/docs/openapi/examples';
        if (! is_dir($examplesDir)) {
            return;
        }

        foreach ((array) glob($examplesDir . '/*.json') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }
    }

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

    public function testGeneratedOpenApiReferencesExternalFakeExamples(): void
    {
        $openApiData = $this->generateOpenApiData(__DIR__ . '/apidoc.openapi.fake.xml');

        $ticketGet = $this->operation($openApiData, '/ticket/{id}', 'get');
        $ticketGetMediaType = $this->responseJsonMediaType($ticketGet);
        $this->assertSame('#/components/examples/TicketFake', $this->fakeExampleRef($ticketGetMediaType));

        $ticketPost = $this->operation($openApiData, '/ticket/{id}', 'post');
        $ticketPostMediaType = $this->requestJsonMediaType($ticketPost);
        $this->assertSame('#/components/examples/TicketParamFake', $this->fakeExampleRef($ticketPostMediaType));

        $ticketsGet = $this->operation($openApiData, '/tickets', 'get');
        $ticketsGetMediaType = $this->responseJsonMediaType($ticketsGet);
        $this->assertSame('#/components/examples/TicketsFake', $this->fakeExampleRef($ticketsGetMediaType));

        $personGet = $this->operation($openApiData, '/person', 'get');
        $personGetMediaType = $this->responseJsonMediaType($personGet);
        $this->assertSame('#/components/examples/PersonFake', $this->fakeExampleRef($personGetMediaType));

        $ticketExampleObject = $this->componentExample($openApiData, 'TicketFake');
        $ticketParamExampleObject = $this->componentExample($openApiData, 'TicketParamFake');
        $ticketsExampleObject = $this->componentExample($openApiData, 'TicketsFake');
        $personExampleObject = $this->componentExample($openApiData, 'PersonFake');
        $this->assertSame('../../Fake/app/src/var/fake/ticket.json', $ticketExampleObject['externalValue']);
        $this->assertSame('./examples/ticket.param.json', $ticketParamExampleObject['externalValue']);
        $this->assertSame('../../Fake/app/src/var/fake/tickets.json', $ticketsExampleObject['externalValue']);
        $this->assertSame('../../Fake/app/src/var/fake/person.json', $personExampleObject['externalValue']);
        $this->assertArrayNotHasKey('value', $ticketExampleObject);

        $ticketRequestExample = $this->readGeneratedExample('ticket.param.json');
        $this->assertSame([
            'title' => 'Cannot login to dashboard',
            'description' => 'When I click login, I get a 500 error',
            'assignee' => 'john.smith',
        ], $ticketRequestExample);
        $this->assertArrayNotHasKey('id', $ticketRequestExample);
        $this->assertArrayNotHasKey('status', $ticketRequestExample);
    }

    /** @return array<string, mixed> */
    private function generateOpenApiData(string $configFile = __DIR__ . '/apidoc.openapi.xml'): array
    {
        $apiDoc = new ApiDoc();
        $apiDoc($configFile);

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

    /** @param array<string, mixed> $mediaType */
    private function fakeExampleRef(array $mediaType): string
    {
        $examples = $mediaType['examples'] ?? null;
        $this->assertIsArray($examples);
        $fakeExample = $examples['fake'] ?? null;
        $this->assertIsArray($fakeExample);
        $ref = $fakeExample['$ref'] ?? null;
        $this->assertIsString($ref);

        return $ref;
    }

    /**
     * @param array<string, mixed> $openApiData
     *
     * @return array{summary: string, externalValue: string}
     */
    private function componentExample(array $openApiData, string $name): array
    {
        $components = $openApiData['components'] ?? null;
        $this->assertIsArray($components);
        $examples = $components['examples'] ?? null;
        $this->assertIsArray($examples);
        $example = $examples[$name] ?? null;
        $this->assertIsArray($example);
        $summary = $example['summary'] ?? null;
        $externalValue = $example['externalValue'] ?? null;
        $this->assertIsString($summary);
        $this->assertIsString($externalValue);

        return [
            'summary' => $summary,
            'externalValue' => $externalValue,
        ];
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private function requestBodySchema(array $operation): array
    {
        $json = $this->requestJsonMediaType($operation);
        $schema = $json['schema'] ?? null;
        $this->assertIsArray($schema);

        /** @var array<string, mixed> $schema */
        return $schema;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private function requestJsonMediaType(array $operation): array
    {
        $requestBody = $operation['requestBody'] ?? null;
        $this->assertIsArray($requestBody);
        $content = $requestBody['content'] ?? null;
        $this->assertIsArray($content);
        $json = $content['application/json'] ?? null;
        $this->assertIsArray($json);

        /** @var array<string, mixed> $json */
        return $json;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private function responseJsonMediaType(array $operation): array
    {
        $responses = $operation['responses'] ?? null;
        $this->assertIsArray($responses);
        $response = $responses['200'] ?? null;
        $this->assertIsArray($response);
        $content = $response['content'] ?? null;
        $this->assertIsArray($content);
        $json = $content['application/json'] ?? null;
        $this->assertIsArray($json);

        /** @var array<string, mixed> $json */
        return $json;
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

    /** @return array<string, mixed> */
    private function readGeneratedExample(string $file): array
    {
        $json = file_get_contents(__DIR__ . '/docs/openapi/examples/' . $file);
        $this->assertIsString($json);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }
}
