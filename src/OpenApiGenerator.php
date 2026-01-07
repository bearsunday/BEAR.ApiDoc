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
use function assert;
use function file_get_contents;
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
            'openapi' => '3.0.0',
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

        $schemaName = $schema->title ?: 'Response';
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

        // Convert file $refs to OpenAPI internal refs
        $this->schemas[$schemaName] = $this->convertRefs($schemaArray);
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
            if ($key === '$ref' && is_string($value) && ! str_starts_with($value, '#')) {
                // Convert file reference to OpenAPI internal reference
                $refSchemaName = $this->resolveRefSchemaName($value);
                $schema[$key] = sprintf('#/components/schemas/%s', $refSchemaName);

                // Also add the referenced schema to components
                $this->addSchemaToComponents($refSchemaName, $value);
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
                return (string) $schemaJson->title;
            }
        }

        // Fallback: convert filename to schema name (age.json -> Age)
        $baseName = pathinfo($refFile, PATHINFO_FILENAME);

        return ucfirst($baseName);
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
