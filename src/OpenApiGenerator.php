<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

use function array_key_exists;
use function array_map;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function json_encode;
use function pathinfo;
use function preg_match_all;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;
use function ucfirst;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const PATHINFO_FILENAME;

/**
 * @psalm-import-type OpenApiSpec from Types
 * @psalm-import-type OpenApiOperation from Types
 * @psalm-import-type OpenApiOperationPartial from Types
 * @psalm-import-type OpenApiResponse from Types
 * @psalm-import-type OpenApiResponses from Types
 * @psalm-import-type OperationBase from Types
 * @psalm-import-type PathParams from Types
 * @psalm-import-type SchemaRef from Types
 */
final class OpenApiGenerator
{
    /** @var OpenApiSpec */
    private array $openApiSpec;

    /** @var array<string, array<string, mixed>> */
    private array $schemas = [];

    /** @var array<string, array{summary: string, externalValue: string}> */
    private array $examples = [];

    /** @var array<string, FakeDataExample> */
    private array $requestExamples = [];

    private readonly JsonFile $jsonFile;

    private readonly FakeDataExampleResolver $fakeDataExampleResolver;

    public function __construct(
        private readonly Config $config,
        private readonly string $requestSchemaDir,
        private readonly string $responseSchemaDir,
        ?JsonFile $jsonFile = null,
        ?FakeDataExampleResolver $fakeDataExampleResolver = null,
    ) {
        $this->jsonFile = $jsonFile ?? new JsonFile();
        $this->fakeDataExampleResolver = $fakeDataExampleResolver ?? new FakeDataExampleResolver($this->config->fakeDataDir, $this->config->docDir);
        $this->openApiSpec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->config->title ?: 'API Documentation',
                'description' => $this->config->description ?: '',
                'version' => '1.0.0',
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
            ],
        ];
    }

    public function generate(): string
    {
        foreach ($this->config->resourceFiles as $meta) {
            $path = $this->config->routes[$meta->uriPath] ?? $meta->uriPath;
            $this->processResource($path, new ReflectionClass($meta->class));
        }

        // Add collected schemas to components
        $this->openApiSpec['components']['schemas'] = $this->schemas;
        if ($this->examples !== []) {
            $this->openApiSpec['components']['examples'] = $this->examples;
        }

        return json_encode($this->openApiSpec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param ReflectionClass<T> $class
     *
     * @template T of object
     */
    private function processResource(string $path, ReflectionClass $class): void
    {
        $docComment = (string) $class->getDocComment();
        [$summary, $description] = (new PhpDoc())($docComment);

        $methods = $class->getMethods();
        $pathItem = [];

        // Extract path parameters from path (e.g., /users/{id} -> ['id'])
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        $pathParams = $matches[1];

        foreach ($methods as $method) {
            $name = $method->getName();
            $isRequestMethod = in_array($name, ['onGet', 'onPut', 'onPost', 'onPatch', 'onDelete']);
            if ($isRequestMethod) {
                $httpMethod = strtolower(substr($name, 2));
                $pathItem[$httpMethod] = $this->processMethod($method, $httpMethod, $summary, $description, $pathParams);
            }
        }

        if ($pathItem !== []) {
            $this->openApiSpec['paths'][$path] = $pathItem;
        }
    }

    /**
     * @param PathParams $pathParams
     *
     * @return OpenApiOperation
     */
    private function processMethod(ReflectionMethod $method, string $httpMethod, string $classSummary, string $classDescription, array $pathParams): array
    {
        $operation = $this->buildOperationBase($method, $classSummary, $classDescription);
        [$operation, $hasRequestSchema] = $this->applyJsonSchemaAttribute($method, $httpMethod, $operation, $pathParams);
        $operation = $this->ensureDefaultResponse($operation);

        return $this->addErrorResponses($operation, $hasRequestSchema, $pathParams);
    }

    /** @return OperationBase */
    private function buildOperationBase(ReflectionMethod $method, string $classSummary, string $classDescription): array
    {
        $docComment = (string) $method->getDocComment();
        [$methodSummary, $methodDescription] = (new PhpDoc())($docComment);

        $summary = trim($methodSummary ?: $classSummary);
        $description = trim($methodDescription ?: $classDescription);

        $operation = [];

        // Use Alps attribute id as operationId if present
        $alpsAttributes = $method->getAttributes(Alps::class);
        if ($alpsAttributes !== []) {
            $alps = $alpsAttributes[0]->newInstance();
            $operation['operationId'] = $alps->id;
        }

        if ($summary !== '') {
            $operation['summary'] = $summary;
        }

        if ($description !== '') {
            $operation['description'] = $description;
        }

        return $operation;
    }

    /**
     * @param OpenApiOperationPartial $operation
     * @param PathParams              $pathParams
     *
     * @return array{0: OpenApiOperationPartial, 1: bool}
     */
    private function applyJsonSchemaAttribute(ReflectionMethod $method, string $httpMethod, array $operation, array $pathParams): array
    {
        $attributes = $method->getAttributes(JsonSchema::class);
        $schemaAttribute = $attributes === [] ? null : $attributes[0]->newInstance();
        $requestSchemaFile = $schemaAttribute instanceof JsonSchema ? $schemaAttribute->params : '';
        $requestSchema = $requestSchemaFile === '' ? null : $this->loadSchema($this->requestSchemaDir, $requestSchemaFile);
        $requestExample = $schemaAttribute instanceof JsonSchema ? $this->resolveRequestExample($method, $httpMethod, $requestSchemaFile, $requestSchema, $schemaAttribute->schema, $pathParams) : null;
        $operation = (new OpenApiInputBuilder())($method, $httpMethod, $operation, $requestSchema, $pathParams);
        if ($requestExample instanceof FakeDataExample) {
            $operation = $this->withRequestExample($operation, $requestExample);
        }

        if (! $schemaAttribute instanceof JsonSchema) {
            return [$operation, false];
        }

        $hasRequestSchema = $schemaAttribute->params !== '';

        $schemaRef = $this->processResponse($schemaAttribute->schema);
        if ($schemaRef !== null) {
            $jsonMediaType = ['schema' => $schemaRef];
            $responseExample = $this->resolveResponseExample($schemaAttribute->schema);
            if ($responseExample instanceof FakeDataExample) {
                $jsonMediaType['examples'] = $responseExample->toMediaTypeExamples();
            }

            /** @var OpenApiResponse $successResponse */
            $successResponse = [
                'description' => 'Successful response',
                'content' => ['application/json' => $jsonMediaType],
            ];
            /** @var array<string, OpenApiResponse> $responses */
            $responses = [];
            $responses['200'] = $successResponse;
            $operation['responses'] = $responses;
        }

        return [$operation, $hasRequestSchema];
    }

    /**
     * @param OpenApiOperationPartial $operation
     *
     * @return OpenApiOperationPartial
     */
    private function withRequestExample(array $operation, FakeDataExample $example): array
    {
        if (! isset($operation['requestBody']['content']['application/json'])) {
            return $operation;
        }

        $operation['requestBody']['content']['application/json']['examples'] = $example->toMediaTypeExamples();

        return $operation;
    }

    /**
     * @param OpenApiOperation $operation
     * @param PathParams       $pathParams
     *
     * @return OpenApiOperation
     */
    private function addErrorResponses(array $operation, bool $hasRequestSchema, array $pathParams): array
    {
        /** @var OpenApiResponses $responses */
        $responses = $operation['responses'];

        if ($hasRequestSchema) {
            /** @var OpenApiResponse $badRequest */
            $badRequest = ['description' => 'Bad Request'];
            $responses['400'] = $badRequest;
        }

        if ($pathParams !== []) {
            /** @var OpenApiResponse $notFound */
            $notFound = ['description' => 'Not Found'];
            $responses['404'] = $notFound;
        }

        $operation['responses'] = $responses;

        return $operation;
    }

    /**
     * @param OpenApiOperationPartial $operation
     *
     * @return OpenApiOperation
     */
    private function ensureDefaultResponse(array $operation): array
    {
        if (! array_key_exists('responses', $operation)) {
            /** @var OpenApiResponse $defaultResponse */
            $defaultResponse = ['description' => 'Successful response'];
            /** @var array<string, OpenApiResponse> $responses */
            $responses = [];
            $responses['200'] = $defaultResponse;
            $operation['responses'] = $responses;
        }

        return $operation;
    }

    /** @return SchemaRef|null */
    private function processResponse(string $schemaFile): ?array
    {
        $schema = $this->loadSchema($this->responseSchemaDir, $schemaFile);

        if (! $schema instanceof \BEAR\ApiDoc\Schema) {
            return null;
        }

        $schemaName = $this->sanitizeSchemaName($schema->title ?: 'Response');
        $this->addSchemaToComponents($schemaName, $schemaFile);

        return [
            '$ref' => sprintf('#/components/schemas/%s', $schemaName),
        ];
    }

    private function loadSchema(string $dir, string $file): ?Schema
    {
        $schemaFile = sprintf('%s/%s', $dir, $file);
        if (! is_file($schemaFile)) {
            return null; // @codeCoverageIgnore
        }

        $fileInfo = new SplFileInfo($schemaFile);
        /** @var ArrayObject<string, string> $emptyDictionary */
        $emptyDictionary = new ArrayObject();

        return new Schema($fileInfo, $this->jsonFile->object($schemaFile), $emptyDictionary);
    }

    private function resolveResponseExample(string $schemaFile): ?FakeDataExample
    {
        $example = $this->fakeDataExampleResolver->responseExample($schemaFile, $this->loadSchema($this->responseSchemaDir, $schemaFile));
        if (! $example instanceof FakeDataExample) {
            return null;
        }

        $this->examples[$example->componentName] = $example->toOpenApiExampleObject();

        return $example;
    }

    /** @param PathParams $pathParams */
    private function resolveRequestExample(ReflectionMethod $method, string $httpMethod, string $requestSchemaFile, ?Schema $requestSchema, string $responseSchemaFile, array $pathParams): ?FakeDataExample
    {
        if (isset($this->requestExamples[$requestSchemaFile])) {
            return $this->requestExamples[$requestSchemaFile];
        }

        $propertyNames = $this->requestBodyPropertyNames($method, $httpMethod, $pathParams);
        $example = $this->fakeDataExampleResolver->requestExample($requestSchemaFile, $requestSchema, $responseSchemaFile, $propertyNames);
        if (! $example instanceof FakeDataExample) {
            return null;
        }

        $this->examples[$example->componentName] = $example->toOpenApiExampleObject();
        $this->requestExamples[$requestSchemaFile] = $example;

        return $example;
    }

    /**
     * @param PathParams $pathParams
     *
     * @return list<string>
     */
    private function requestBodyPropertyNames(ReflectionMethod $method, string $httpMethod, array $pathParams): array
    {
        if (! in_array($httpMethod, ['post', 'put', 'patch'], true)) {
            return [];
        }

        $propertyNames = [];
        foreach ((new InputParamExpander())($method) as $param) {
            $paramName = $param->getName();
            if (in_array($paramName, $pathParams, true)) {
                continue;
            }

            $propertyNames[] = $paramName;
        }

        return $propertyNames;
    }

    private function addSchemaToComponents(string $schemaName, string $schemaFile): void
    {
        $schemaName = $this->sanitizeSchemaName($schemaName);
        if (isset($this->schemas[$schemaName])) {
            return;
        }

        $schemaPath = sprintf('%s/%s', $this->responseSchemaDir, $schemaFile);
        if (! is_file($schemaPath)) {
            return; // @codeCoverageIgnore
        }

        // Clean up and convert for OpenAPI compatibility
        $cleanedSchema = $this->cleanSchemaForOpenApi($this->jsonFile->assoc($schemaPath));
        $this->schemas[$schemaName] = $this->convertRefs($cleanedSchema);
    }

    /**
     * Remove JSON Schema properties not allowed in OpenAPI Schema Object
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedAssignment
     * @psalm-suppress MixedArrayAssignment
     * @psalm-suppress MixedArgumentTypeCoercion
     * @psalm-suppress PossiblyUndefinedArrayOffset
     */
    private function cleanSchemaForOpenApi(array $schema): array
    {
        $this->extractDefinitions($schema);
        $schema = $this->removeDisallowedProperties($schema);

        return $this->cleanNestedSchemas($schema);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @psalm-suppress MixedAssignment
     */
    private function extractDefinitions(array $schema): void
    {
        if (! isset($schema['definitions']) || ! is_array($schema['definitions'])) {
            return;
        }

        foreach ($schema['definitions'] as $defName => $definition) {
            $schemaName = ucfirst((string) $defName);
            /** @var array<string, mixed> $definition */
            if (! isset($this->schemas[$schemaName])) {
                $this->schemas[$schemaName] = $this->cleanSchemaForOpenApi($definition);
            }
        }
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function removeDisallowedProperties(array $schema): array
    {
        $disallowedProperties = ['$id', 'id', '$schema', 'definitions', 'dependencies'];

        foreach ($disallowedProperties as $prop) {
            unset($schema[$prop]);
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedAssignment
     * @psalm-suppress MixedArrayAssignment
     * @psalm-suppress MixedArgumentTypeCoercion
     */
    private function cleanNestedSchemas(array $schema): array
    {
        $nestedKeys = ['properties', 'items', 'allOf', 'oneOf', 'anyOf', 'additionalProperties'];
        $arrayKeys = ['allOf', 'oneOf', 'anyOf'];

        foreach ($nestedKeys as $key) {
            if (! isset($schema[$key])) {
                continue;
            }

            if (! is_array($schema[$key])) {
                continue;
            }

            $schema[$key] = $this->cleanNestedSchema($key, $schema[$key], $arrayKeys);
        }

        return $schema;
    }

    /**
     * @param array<string|int, mixed> $nested
     * @param array<string>            $arrayKeys
     *
     * @return array<string|int, mixed>
     *
     * @psalm-suppress MixedAssignment
     * @psalm-suppress MixedArrayAssignment
     * @psalm-suppress MixedArgumentTypeCoercion
     */
    private function cleanNestedSchema(string $key, array $nested, array $arrayKeys): array
    {
        if ($key === 'properties' || in_array($key, $arrayKeys, true)) {
            foreach ($nested as $subKey => $subSchema) {
                if (is_array($subSchema)) {
                    $nested[$subKey] = $this->cleanSchemaForOpenApi($subSchema); // @phpstan-ignore argument.type
                }
            }

            return $nested;
        }

        /** @var array<string, mixed> $nested */
        return $this->cleanSchemaForOpenApi($nested);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedAssignment
     */
    private function convertRefs(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                if (str_starts_with($value, '#/definitions/')) {
                    // Convert #/definitions/name to #/components/schemas/Name
                    $defName = substr($value, 14); // Remove '#/definitions/'
                    $schema[$key] = sprintf('#/components/schemas/%s', ucfirst($defName));
                } elseif (! str_starts_with($value, '#')) {
                    // Convert file reference to OpenAPI internal reference
                    $refSchemaName = $this->resolveRefSchemaName($value);
                    $schema[$key] = sprintf('#/components/schemas/%s', $refSchemaName);

                    // Also add the referenced schema to components
                    $this->addSchemaToComponents($refSchemaName, $value);
                }
            } elseif (is_array($value)) {
                /** @psalm-var array<string, mixed> $value */   // phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.NoAssignment
                $schema[$key] = $this->convertRefs($value);
            }
        }

        return $schema;
    }

    private function resolveRefSchemaName(string $refFile): string
    {
        // Load the referenced schema to get its title
        $schemaPath = sprintf('%s/%s', $this->responseSchemaDir, $refFile);
        if (is_file($schemaPath)) {
            $schemaJson = $this->jsonFile->object($schemaPath);
            if (isset($schemaJson->title) && is_string($schemaJson->title)) {
                return $this->sanitizeSchemaName($schemaJson->title);
            }
        }

        // Fallback: convert filename to schema name (age.json -> Age)
        // @codeCoverageIgnoreStart
        $baseName = pathinfo($refFile, PATHINFO_FILENAME);

        return ucfirst($baseName);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Sanitize schema name to be a valid OpenAPI component name (no spaces)
     */
    private function sanitizeSchemaName(string $name): string
    {
        // Convert "Collection of Tickets" -> "CollectionOfTickets"
        $words = explode(' ', $name);
        $words = array_map(ucfirst(...), $words);

        return implode('', $words);
    }
}
