<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

use function array_key_exists;
use function array_map;
use function assert;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_object;
use function is_string;
use function json_decode;
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
 * @psalm-import-type OpenApiParameter from Types
 * @psalm-import-type OpenApiResponse from Types
 * @psalm-import-type OpenApiResponses from Types
 * @psalm-import-type OperationBase from Types
 * @psalm-import-type ParameterLocation from Types
 * @psalm-import-type PathParams from Types
 * @psalm-import-type SchemaRef from Types
 */
final class OpenApiGenerator
{
    /** @var OpenApiSpec */
    private array $openApiSpec;

    /** @var array<string, array<string, mixed>> */
    private array $schemas = [];

    public function __construct(
        private readonly Config $config,
        private readonly string $requestSchemaDir,
        private readonly string $responseSchemaDir
    ) {
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
                $pathItem[$httpMethod] = $this->processMethod($method, $summary, $description, $pathParams);
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
    private function processMethod(ReflectionMethod $method, string $classSummary, string $classDescription, array $pathParams): array
    {
        $operation = $this->buildOperationBase($method, $classSummary, $classDescription);
        [$operation, $hasRequestSchema] = $this->applyJsonSchemaAttribute($method, $operation, $pathParams);
        $operation = $this->ensurePathParameters($operation, $pathParams);
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
     * @param array{operationId?: string, summary?: string, description?: string, parameters?: list<OpenApiParameter>, responses?: OpenApiResponses} $operation
     * @param PathParams                                                                                                                             $pathParams
     *
     * @return array{0: array{operationId?: string, summary?: string, description?: string, parameters?: list<OpenApiParameter>, responses?: OpenApiResponses}, 1: bool}
     */
    private function applyJsonSchemaAttribute(ReflectionMethod $method, array $operation, array $pathParams): array
    {
        $attributes = $method->getAttributes(JsonSchema::class);
        if ($attributes === []) {
            return [$operation, false];
        }

        $schemaAttribute = $attributes[0]->newInstance();
        $hasRequestSchema = $schemaAttribute->params !== '';

        $parameters = $this->processParameters($method, $schemaAttribute->params, $pathParams);
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $schemaRef = $this->processResponse($schemaAttribute->schema);
        if ($schemaRef !== null) {
            /** @var OpenApiResponse $successResponse */
            $successResponse = [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => ['schema' => $schemaRef],
                ],
            ];
            /** @var array<string, OpenApiResponse> $responses */
            $responses = [];
            $responses['200'] = $successResponse;
            $operation['responses'] = $responses;
        }

        return [$operation, $hasRequestSchema];
    }

    /**
     * @param array{operationId?: string, summary?: string, description?: string, parameters?: list<OpenApiParameter>, responses: OpenApiResponses} $operation
     * @param PathParams                                                                                                                            $pathParams
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
     * @param array{operationId?: string, summary?: string, description?: string, parameters?: list<OpenApiParameter>, responses?: OpenApiResponses} $operation
     * @param PathParams                                                                                                                             $pathParams
     *
     * @return array{operationId?: string, summary?: string, description?: string, parameters?: list<OpenApiParameter>, responses?: OpenApiResponses}
     */
    private function ensurePathParameters(array $operation, array $pathParams): array
    {
        if ($pathParams !== [] && ! array_key_exists('parameters', $operation)) {
            $operation['parameters'] = $this->createPathParameters($pathParams);
        }

        return $operation;
    }

    /**
     * @param array{operationId?: string, summary?: string, description?: string, parameters?: list<OpenApiParameter>, responses?: OpenApiResponses} $operation
     *
     * @return array{operationId?: string, summary?: string, description?: string, parameters?: list<OpenApiParameter>, responses: OpenApiResponses}
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

    /**
     * @param PathParams $pathParams
     *
     * @return list<OpenApiParameter>
     */
    private function processParameters(ReflectionMethod $method, string $schemaFile, array $pathParams): array
    {
        /** @var list<OpenApiParameter> $parameters */
        $parameters = [];
        $schema = $this->loadSchema($this->requestSchemaDir, $schemaFile);

        if (! $schema instanceof \BEAR\ApiDoc\Schema) {
            return [];
        }

        $methodParams = $method->getParameters();
        foreach ($methodParams as $param) {
            $paramName = $param->getName();
            $paramSchema = $schema->props[$paramName] ?? null;

            if ($paramSchema === null) {
                continue; // @codeCoverageIgnore
            }

            $paramType = $param->getType();
            $typeName = 'string';
            if ($paramType instanceof ReflectionNamedType) {
                $typeName = $paramType->getName();
            }

            $isPathParam = in_array($paramName, $pathParams, true);
            /** @var ParameterLocation $location */
            $location = $isPathParam ? 'path' : 'query';
            /** @var OpenApiParameter $parameter */
            $parameter = [
                'name' => $paramName,
                'in' => $location,
                'required' => $isPathParam || ! $param->isOptional(),
                'schema' => ['type' => $this->convertPhpTypeToOpenApi($typeName)],
            ];

            if ($paramSchema->description !== '') {
                $parameter['description'] = $paramSchema->description;
            }

            if ($paramSchema->example !== '') {
                $parameter['example'] = $paramSchema->example;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @param PathParams $pathParams
     *
     * @return list<OpenApiParameter>
     */
    private function createPathParameters(array $pathParams): array
    {
        /** @var list<OpenApiParameter> $parameters */
        $parameters = [];
        foreach ($pathParams as $paramName) {
            /** @var OpenApiParameter $parameter */
            $parameter = [
                'name' => $paramName,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
            $parameters[] = $parameter;
        }

        return $parameters;
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

        $schemaJson = json_decode((string) file_get_contents($schemaFile));
        if (! is_object($schemaJson)) {
            return null; // @codeCoverageIgnore
        }

        $fileInfo = new SplFileInfo($schemaFile);
        /** @var ArrayObject<string, string> $emptyDictionary */
        $emptyDictionary = new ArrayObject();

        return new Schema($fileInfo, $schemaJson, $emptyDictionary);
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

        $schemaJson = json_decode((string) file_get_contents($schemaPath));
        if (! is_object($schemaJson)) {
            return; // @codeCoverageIgnore
        }

        // Convert to array for OpenAPI
        /** @var array<string, mixed> $schemaArray */
        $schemaArray = json_decode((string) json_encode($schemaJson), true);

        // Clean up and convert for OpenAPI compatibility
        $cleanedSchema = $this->cleanSchemaForOpenApi($schemaArray);
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
            $schemaJson = json_decode((string) file_get_contents($schemaPath));
            assert(is_object($schemaJson) || $schemaJson === null);
            if (is_object($schemaJson) && isset($schemaJson->title) && is_string($schemaJson->title)) {
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

    /** @codeCoverageIgnore */
    private function convertPhpTypeToOpenApi(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        }; // phpcs:ignore SlevomatCodingStandard.PHP.UselessSemicolon.UselessSemicolon
    }
}
