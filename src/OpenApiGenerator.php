<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\Resource\Annotation\JsonSchema;
use Doctrine\Common\Annotations\Reader;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

use function array_key_exists;
use function file_get_contents;
use function in_array;
use function is_file;
use function is_object;
use function json_decode;
use function json_encode;
use function sprintf;
use function strtolower;
use function substr;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

final class OpenApiGenerator
{
    /** @var array<string, mixed> */
    private array $openApiSpec;

    /** @var array<string, mixed> */
    private array $schemas = [];

    public function __construct(
        private readonly Reader $reader,
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
        $this->openApiSpec['components']['schemas'] = $this->schemas;

        return (string) json_encode($this->openApiSpec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function processResource(string $path, ReflectionClass $class): void
    {
        $docComment = (string) $class->getDocComment();
        [$summary, $description, ] = (new PhpDoc())($docComment);

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
            $this->openApiSpec['paths'][$path] = $pathItem;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function processMethod(ReflectionMethod $method, string $classSummary, string $classDescription): array
    {
        $docComment = (string) $method->getDocComment();
        [$methodSummary, $methodDescription, ] = (new PhpDoc())($docComment);

        $operation = [
            'summary' => $methodSummary ?: $classSummary,
            'description' => $methodDescription ?: $classDescription,
        ];

        // Get JSON Schema annotation
        $schemaAnnotation = $this->reader->getMethodAnnotation($method, JsonSchema::class);

        if ($schemaAnnotation instanceof JsonSchema) {
            // Process request parameters
            $parameters = $this->processParameters($method, $schemaAnnotation->params);
            if ($parameters !== []) {
                $operation['parameters'] = $parameters;
            }

            // Process response
            $response = $this->processResponse($schemaAnnotation->schema);
            if ($response !== null) {
                $operation['responses'] = [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => $response,
                            ],
                        ],
                    ],
                ];
            }
        }

        // Add default response if no response defined
        if (! array_key_exists('responses', $operation)) {
            $operation['responses'] = [
                '200' => [
                    'description' => 'Successful response',
                ],
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

            $parameter = [
                'name' => $paramName,
                'in' => 'query',
                'description' => $paramSchema->description,
                'required' => ! $param->isOptional(),
                'schema' => [
                    'type' => $this->convertPhpTypeToOpenApi($param->getType()?->getName() ?? 'string'),
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
        $this->schemas[$schemaName] = json_decode((string) json_encode($schemaJson), true);
    }

    private function convertPhpTypeToOpenApi(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }
}
