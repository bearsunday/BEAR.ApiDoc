<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\Resource\Annotation\JsonSchema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

use function array_key_exists;
use function array_map;
use function array_search;
use function array_values;
use function assert;
use function count;
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
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;
use function ucfirst;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const PATHINFO_FILENAME;

final class OpenApiGenerator
{
    /** @var array<string, mixed> */
    private array $openApiSpec;

    /** @var array<string, mixed> */
    private array $schemas = [];

    public function __construct(
        private readonly Config $config,
        private readonly string $requestSchemaDir,
        private readonly string $responseSchemaDir
    ) {
        $this->openApiSpec = [
            '$schema' => 'https://spec.openapis.org/oas/3.0/schema/2024-10-18',
            'openapi' => '3.0.3',
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
        assert(is_array($this->openApiSpec['components']));
        $this->openApiSpec['components']['schemas'] = $this->schemas;

        return (string) json_encode($this->openApiSpec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function processResource(string $path, ReflectionClass $class): void
    {
        $docComment = (string) $class->getDocComment();
        [$summary, $description] = (new PhpDoc())($docComment);

        $methods = $class->getMethods();
        $pathItem = [];

        foreach ($methods as $method) {
            $name = $method->getName();
            $isRequestMethod = in_array($name, ['onGet', 'onPut', 'onPost', 'onPatch', 'onDelete']);
            if ($isRequestMethod) {
                $httpMethod = strtolower(substr($name, 2));
                $pathItem[$httpMethod] = $this->processMethod($method, $summary, $description);
            }
        }

        if ($pathItem !== []) {
            assert(is_array($this->openApiSpec['paths']));
            $this->openApiSpec['paths'][$path] = $pathItem;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function processMethod(ReflectionMethod $method, string $classSummary, string $classDescription): array
    {
        $docComment = (string) $method->getDocComment();
        [$methodSummary, $methodDescription] = (new PhpDoc())($docComment);

        $operation = [
            'summary' => $methodSummary ?: $classSummary,
            'description' => $methodDescription ?: $classDescription,
        ];

        // Get JSON Schema attribute
        $attributes = $method->getAttributes(JsonSchema::class);
        $schemaAttribute = $attributes !== [] ? $attributes[0]->newInstance() : null;

        if ($schemaAttribute instanceof JsonSchema) {
            // Process request parameters
            $parameters = $this->processParameters($method, $schemaAttribute->params);
            if ($parameters !== []) {
                $operation['parameters'] = $parameters;
            }

            // Process response
            $response = $this->processResponse($schemaAttribute->schema);
            if ($response !== null) {
                $operation['responses'] = [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => ['schema' => $response],
                        ],
                    ],
                ];
            }
        }

        // Add default response if no response defined
        if (! array_key_exists('responses', $operation)) {
            $operation['responses'] = [
                '200' => ['description' => 'Successful response'],
            ];
        }

        return $operation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function processParameters(ReflectionMethod $method, string $schemaFile): array
    {
        $parameters = [];
        $schema = $this->loadSchema($this->requestSchemaDir, $schemaFile);

        if ($schema === null) {
            return [];
        }

        $methodParams = $method->getParameters();
        foreach ($methodParams as $param) {
            $paramName = $param->getName();
            $paramSchema = $schema->props[$paramName] ?? null;

            if ($paramSchema === null) {
                continue;
            }

            $paramType = $param->getType();
            $typeName = 'string';
            if ($paramType instanceof ReflectionNamedType) {
                $typeName = $paramType->getName();
            }

            $parameter = [
                'name' => $paramName,
                'in' => 'query',
                'description' => $paramSchema->description,
                'required' => ! $param->isOptional(),
                'schema' => [
                    'type' => $this->convertPhpTypeToOpenApi($typeName),
                ],
            ];

            if ($paramSchema->example !== '') {
                $parameter['example'] = $paramSchema->example;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function processResponse(string $schemaFile): ?array
    {
        $schema = $this->loadSchema($this->responseSchemaDir, $schemaFile);

        if ($schema === null) {
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
            return null;
        }

        $schemaJson = json_decode((string) file_get_contents($schemaFile));
        if (! is_object($schemaJson)) {
            return null;
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
            return;
        }

        $schemaJson = json_decode((string) file_get_contents($schemaPath));
        if (! is_object($schemaJson)) {
            return;
        }

        // Convert to array for OpenAPI
        /** @var array<string, mixed> $schemaArray */
        $schemaArray = json_decode((string) json_encode($schemaJson), true);

        // Clean up and convert for OpenAPI 3.0 compatibility
        $cleanedSchema = $this->cleanSchemaForOpenApi($schemaArray);
        $this->schemas[$schemaName] = $this->convertRefs($cleanedSchema);
    }

    /**
     * Remove JSON Schema properties not allowed in OpenAPI 3.0
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
        // Extract definitions and add them to components/schemas
        if (isset($schema['definitions']) && is_array($schema['definitions'])) {
            foreach ($schema['definitions'] as $defName => $definition) {
                $schemaName = ucfirst((string) $defName);
                /** @var array<string, mixed> $definition */
                if (! isset($this->schemas[$schemaName])) {
                    $this->schemas[$schemaName] = $this->cleanSchemaForOpenApi($definition);
                }
            }
        }

        // Properties not allowed in OpenAPI 3.0 Schema Object
        $disallowedProperties = ['$id', 'id', '$schema', 'definitions', 'dependencies'];

        foreach ($disallowedProperties as $prop) {
            unset($schema[$prop]);
        }

        // Convert 'examples' to 'example' (OpenAPI 3.0 uses singular)
        if (isset($schema['examples']) && is_array($schema['examples']) && $schema['examples'] !== []) {
            $schema['example'] = $schema['examples'][0];
            unset($schema['examples']);
        }

        // Handle type arrays (JSON Schema) -> nullable (OpenAPI 3.0)
        if (isset($schema['type']) && is_array($schema['type'])) {
            $types = $schema['type'];
            $nullIndex = array_search('null', $types, true);
            if ($nullIndex !== false) {
                unset($types[$nullIndex]);
                $schema['nullable'] = true;
            }

            $types = array_values($types);
            $schema['type'] = count($types) === 1 ? $types[0] : 'object';
        }

        // Handle $ref with sibling properties (not allowed in OpenAPI 3.0)
        // Convert to allOf format to preserve sibling properties
        if (isset($schema['$ref']) && count($schema) > 1) {
            $ref = $schema['$ref'];
            unset($schema['$ref']);
            $schema = [
                'allOf' => [['$ref' => $ref]],
            ] + $schema;
        }

        // Recursively clean nested schemas
        foreach (['properties', 'items', 'allOf', 'oneOf', 'anyOf', 'additionalProperties'] as $nested) {
            if (! isset($schema[$nested])) {
                continue;
            }

            if ($nested === 'properties' && is_array($schema[$nested])) {
                foreach ($schema[$nested] as $propName => $propSchema) {
                    if (is_array($propSchema)) {
                        $schema[$nested][$propName] = $this->cleanSchemaForOpenApi($propSchema);
                    }
                }
            } elseif (in_array($nested, ['allOf', 'oneOf', 'anyOf'], true) && is_array($schema[$nested])) {
                foreach ($schema[$nested] as $i => $subSchema) {
                    if (is_array($subSchema)) {
                        $schema[$nested][$i] = $this->cleanSchemaForOpenApi($subSchema);
                    }
                }
            } elseif (is_array($schema[$nested])) {
                $schema[$nested] = $this->cleanSchemaForOpenApi($schema[$nested]);
            }
        }

        return $schema;
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
            if (is_object($schemaJson) && isset($schemaJson->title)) {
                return $this->sanitizeSchemaName((string) $schemaJson->title);
            }
        }

        // Fallback: convert filename to schema name (age.json -> Age)
        $baseName = pathinfo($refFile, PATHINFO_FILENAME);

        return ucfirst($baseName);
    }

    /**
     * Sanitize schema name to be a valid OpenAPI component name (no spaces)
     */
    private function sanitizeSchemaName(string $name): string
    {
        // Convert "Collection of Tickets" -> "CollectionOfTickets"
        $words = explode(' ', $name);
        $words = array_map('ucfirst', $words);

        return implode('', $words);
    }

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
