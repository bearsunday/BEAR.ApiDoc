<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Fake\Ro\PathOnlyInputFixture;
use BEAR\ApiDoc\Fake\Ro\TypedInputFixture;
use FakeVendor\FakeProject\Resource\App\ArrayData;
use FakeVendor\FakeProject\Resource\App\Contact;
use FakeVendor\FakeProject\Resource\App\Index;
use FakeVendor\FakeProject\Resource\App\User;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFileInfo;

use function file_get_contents;
use function is_object;
use function json_decode;

class OpenApiRequestBuilderTest extends TestCase
{
    public function testGetBuildsQueryAndPathParametersFromSchema(): void
    {
        $operation = $this->build(User::class, 'onGet', 'get', $this->schema('user.param.json'), ['id']);

        $this->assertSame(
            [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => 'Unique identifier for the user',
                    'example' => 'usr_abc123',
                ],
                [
                    'name' => 'options',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                    'description' => 'Display options',
                    'example' => 'full',
                ],
            ],
            $operation['parameters'] ?? null,
        );
        $this->assertArrayNotHasKey('requestBody', $operation);
    }

    public function testPostBuildsRequiredRequestBodyWithoutJsonSchema(): void
    {
        $operation = $this->build(Contact::class, 'onPost', 'post');
        $requestBody = $this->requestBody($operation);
        $schema = $this->requestBodySchema($operation);
        $properties = $this->requestBodyProperties($operation);

        $this->assertArrayNotHasKey('parameters', $operation);
        $this->assertTrue($requestBody['required'] ?? null);
        $this->assertSame(['name', 'email', 'subject'], $schema['required'] ?? null);
        $this->assertSame('Contact name for display', $properties['name']['description'] ?? null);
        $this->assertSame('integer', $properties['age']['type'] ?? null);
    }

    public function testPutBuildsOptionalRequestBodyAndKeepsPathParameter(): void
    {
        $operation = $this->build(User::class, 'onPut', 'put', $this->schema('user.param.json'), ['id']);
        $requestBody = $this->requestBody($operation);
        $schema = $this->requestBodySchema($operation);
        $properties = $this->requestBodyProperties($operation);

        $this->assertSame(
            [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => 'Unique identifier for the user',
                    'example' => 'usr_abc123',
                ],
            ],
            $operation['parameters'] ?? null,
        );
        $this->assertArrayNotHasKey('required', $requestBody);
        $this->assertArrayNotHasKey('required', $schema);
        $this->assertSame('boolean', $properties['enabled']['type'] ?? null);
        $this->assertSame('true', $properties['enabled']['example'] ?? null);
    }

    public function testUrlPathParameterIsAddedWhenMethodHasNoMatchingArgument(): void
    {
        $operation = $this->build(PathOnlyInputFixture::class, 'onPut', 'put', null, ['id']);

        $this->assertSame(
            [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ],
            ],
            $operation['parameters'] ?? null,
        );
        $this->assertArrayNotHasKey('requestBody', $operation);
    }

    public function testNoInputAndNoPathLeavesOperationUnchanged(): void
    {
        $operation = $this->build(Index::class, 'onGet', 'get');

        $this->assertSame([], $operation);
    }

    public function testArrayParameterTypeIsPreserved(): void
    {
        $operation = $this->build(ArrayData::class, 'onGet', 'get');
        $parameters = $this->parameters($operation);

        $this->assertSame('array', $parameters[0]['schema']['type'] ?? null);
    }

    public function testFloatAndBoolParameterTypesAreConverted(): void
    {
        $operation = $this->build(TypedInputFixture::class, 'onGet', 'get');
        $parameters = $this->parameters($operation);

        $this->assertSame('number', $parameters[0]['schema']['type'] ?? null);
        $this->assertSame('boolean', $parameters[1]['schema']['type'] ?? null);
    }

    /**
     * @param class-string $class
     * @param list<string> $pathParams
     *
     * @return array<string, mixed>
     */
    private function build(string $class, string $method, string $httpMethod, ?Schema $schema = null, array $pathParams = []): array
    {
        return (new OpenApiRequestBuilder())(new ReflectionMethod($class, $method), $httpMethod, [], $schema, $pathParams);
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array{content: array{'application/json': array{schema: array{type?: string, properties?: array<string, array<string, mixed>>, required?: list<string>}}}, required?: bool}
     */
    private function requestBody(array $operation): array
    {
        $requestBody = $operation['requestBody'] ?? null;
        $this->assertIsArray($requestBody);

        /** @var array{content: array{'application/json': array{schema: array{type?: string, properties?: array<string, array<string, mixed>>, required?: list<string>}}}, required?: bool} $requestBody */
        return $requestBody;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array{type?: string, properties?: array<string, array<string, mixed>>, required?: list<string>}
     */
    private function requestBodySchema(array $operation): array
    {
        return $this->requestBody($operation)['content']['application/json']['schema'];
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, array<string, mixed>>
     */
    private function requestBodyProperties(array $operation): array
    {
        $properties = $this->requestBodySchema($operation)['properties'] ?? null;
        $this->assertIsArray($properties);

        /** @var array<string, array<string, mixed>> $properties */
        return $properties;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return list<array{name: string, in: string, required: bool, schema: array{type: string}, description?: string, example?: mixed}>
     */
    private function parameters(array $operation): array
    {
        $parameters = $operation['parameters'] ?? null;
        $this->assertIsArray($parameters);

        /** @var list<array{name: string, in: string, required: bool, schema: array{type: string}, description?: string, example?: mixed}> $parameters */
        return $parameters;
    }

    private function schema(string $file): Schema
    {
        $schemaPath = __DIR__ . '/Fake/app/src/var/json_schema/' . $file;
        $schemaJson = json_decode((string) file_get_contents($schemaPath));
        $this->assertTrue(is_object($schemaJson));

        /** @var ArrayObject<string, string> $emptyDictionary */
        $emptyDictionary = new ArrayObject();

        return new Schema(new SplFileInfo($schemaPath), $schemaJson, $emptyDictionary);
    }
}
