<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

use function file_get_contents;
use function in_array;
use function is_file;
use function is_object;
use function is_string;
use function json_decode;
use function pathinfo;
use function preg_match;
use function sprintf;
use function strtoupper;
use function substr;
use function ucfirst;

use const PATHINFO_FILENAME;

/**
 * Generates single-page HTML API documentation
 *
 * @psalm-import-type HtmlParamArray from Types
 * @psalm-import-type HtmlMethodArray from Types
 * @psalm-import-type HtmlObjectArray from Types
 * @psalm-import-type HtmlRelationArray from Types
 */
final class HtmlGenerator
{
    /** @var array<string, array<string, HtmlMethodArray>> */
    private array $endpoints = [];

    /** @var array<string, HtmlObjectArray> */
    private array $objects = [];

    /** @var array<string, array{embeds: array<HtmlRelationArray>, links: array<HtmlRelationArray>}> */
    private array $objectRelations = [];

    private readonly HtmlRenderer $renderer;

    /** @var ArrayObject<string, string> */
    private readonly ArrayObject $semanticDictionary;

    /**
     * @param ArrayObject<string, string>|null $semanticDictionary
     */
    public function __construct(
        private readonly Config $config,
        private readonly string $requestSchemaDir,
        private readonly string $responseSchemaDir,
        ?ArrayObject $semanticDictionary = null,
    ) {
        $this->renderer = new HtmlRenderer();
        /** @var ArrayObject<string, string> $emptyDictionary */
        $emptyDictionary = new ArrayObject();
        $this->semanticDictionary = $semanticDictionary ?? $emptyDictionary;
    }

    public function generate(): string
    {
        foreach ($this->config->resourceFiles as $meta) {
            $path = $this->config->routes[$meta->uriPath] ?? $meta->uriPath;
            $this->processResource($path, new ReflectionClass($meta->class));
        }

        return $this->renderer->render(
            $this->config->title,
            $this->config->description,
            $this->endpoints,
            $this->objects,
            $this->objectRelations,
        );
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function processResource(string $path, ReflectionClass $class): void
    {
        $methods = $class->getMethods();

        foreach ($methods as $method) {
            $name = $method->getName();
            if (! in_array($name, ['onGet', 'onPut', 'onPost', 'onPatch', 'onDelete'])) {
                continue;
            }

            $httpMethod = strtoupper(substr($name, 2));
            $this->processMethod($path, $httpMethod, $method);
        }
    }

    private function processMethod(string $path, string $httpMethod, ReflectionMethod $method): void
    {
        [$summary, $methodDescription, $paramDescriptions] = $this->extractPhpDoc($method);
        [$requestSchema, $responseSchemaName] = $this->extractJsonSchema($method);
        [$embeds, $links] = $this->extractEmbedsAndLinks($method);
        $alpsIds = $this->extractAlpsIds($method);

        if ($responseSchemaName !== null && ($embeds !== [] || $links !== [])) {
            $this->addObjectRelations($responseSchemaName, $embeds, $links);
        }

        $params = $this->buildParams($method, $requestSchema, $paramDescriptions);

        if (! isset($this->endpoints[$path])) {
            $this->endpoints[$path] = [];
        }

        $this->endpoints[$path][$httpMethod] = [
            'summary' => $summary,
            'description' => $methodDescription,
            'params' => $params,
            'response' => $responseSchemaName,
            'embeds' => $embeds,
            'links' => $links,
            'alps' => $alpsIds,
        ];
    }

    /**
     * @return array<string>
     */
    private function extractAlpsIds(ReflectionMethod $method): array
    {
        $ids = [];
        foreach ($method->getAttributes(Alps::class) as $attr) {
            $alps = $attr->newInstance();
            $ids[] = $alps->id;
        }

        return $ids;
    }

    /**
     * @return array{string, string, array<string, string>}
     */
    private function extractPhpDoc(ReflectionMethod $method): array
    {
        $summary = '';
        $methodDescription = '';
        $paramDescriptions = [];
        $docComment = $method->getDocComment();

        if (is_string($docComment)) {
            $factory = DocBlockFactory::createInstance();
            $docBlock = $factory->create($docComment);
            $summary = $docBlock->getSummary();
            $methodDescription = (string) $docBlock->getDescription();
            foreach ($docBlock->getTagsByName('param') as $param) {
                /** @var \phpDocumentor\Reflection\DocBlock\Tags\Param $param */
                $paramDescriptions[(string) $param->getVariableName()] = (string) $param->getDescription();
            }
        }

        return [$summary, $methodDescription, $paramDescriptions];
    }

    /**
     * @return array{Schema|null, string|null}
     */
    private function extractJsonSchema(ReflectionMethod $method): array
    {
        $requestSchema = null;
        $responseSchemaName = null;
        $attributes = $method->getAttributes(JsonSchema::class);

        if ($attributes === []) {
            return [$requestSchema, $responseSchemaName];
        }

        $schemaAttr = $attributes[0]->newInstance();
        if ($schemaAttr->params !== '') {
            $requestSchema = $this->loadSchema($this->requestSchemaDir, $schemaAttr->params);
        }

        if ($schemaAttr->schema !== '') {
            $responseSchema = $this->loadSchema($this->responseSchemaDir, $schemaAttr->schema);
            if ($responseSchema !== null) {
                $responseSchemaName = $responseSchema->title ?: ucfirst(pathinfo($schemaAttr->schema, PATHINFO_FILENAME));
                $this->addObject($responseSchemaName, $responseSchema);
            }
        }

        return [$requestSchema, $responseSchemaName];
    }

    /**
     * @return array{array<array{rel: string, src: string}>, array<array{rel: string, href: string}>}
     */
    private function extractEmbedsAndLinks(ReflectionMethod $method): array
    {
        $embeds = [];
        $links = [];

        foreach ($method->getAttributes(Embed::class) as $attr) {
            $embed = $attr->newInstance();
            $embeds[] = ['rel' => $embed->rel, 'src' => $embed->src];
        }

        foreach ($method->getAttributes(Link::class) as $attr) {
            $link = $attr->newInstance();
            $links[] = ['rel' => $link->rel, 'href' => $link->href];
        }

        return [$embeds, $links];
    }

    /**
     * @param array<string, string> $paramDescriptions
     *
     * @return array<HtmlParamArray>
     */
    private function buildParams(ReflectionMethod $method, ?Schema $requestSchema, array $paramDescriptions): array
    {
        $params = [];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();
            $typeName = $paramType instanceof ReflectionNamedType ? $paramType->getName() : 'string';

            $description = '';
            $example = '';
            $constraints = [];

            if ($requestSchema !== null && isset($requestSchema->props[$paramName])) {
                $prop = $requestSchema->props[$paramName];
                $description = $prop->description;
                $example = $prop->example;
                $constraints = $prop->constraints->constrains;
            }

            if ($description === '') {
                $description = $paramDescriptions[$paramName] ?? '';
            }

            $alpsTitle = $this->semanticDictionary[$paramName] ?? null;

            $params[] = [
                'name' => $paramName,
                'type' => $this->normalizeType($typeName),
                'description' => $description,
                'required' => ! $param->isOptional(),
                'example' => $example,
                'constraints' => $constraints,
                'alps' => $alpsTitle,
            ];
        }

        return $params;
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

    private function addObject(string $name, Schema $schema): void
    {
        if (isset($this->objects[$name])) {
            return;
        }

        $properties = [];
        $arrayItemType = null;

        if ($schema->type === 'array') {
            $arrayItemType = $this->getArrayItemType($schema);
        }

        foreach ($schema->props as $propName => $prop) {
            if ($propName === '_links' || $propName === '_embedded') {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $formatValue = $prop->constraints->constrains['format'] ?? null;
            $format = is_string($formatValue) ? $formatValue : null;
            $constraints = $prop->constraints->constrains;
            unset($constraints['format']);

            $properties[] = [
                'name' => $propName,
                'type' => $prop->type,
                'description' => $prop->description,
                'example' => $prop->example,
                'format' => $format,
                'constraints' => $constraints,
            ];
        }

        $this->objects[$name] = [
            'name' => $name,
            'properties' => $properties,
            'arrayItemType' => $arrayItemType,
        ];
    }

    private function getArrayItemType(Schema $schema): ?string
    {
        $schemaFile = $schema->file->getPathname();
        $schemaJson = json_decode((string) file_get_contents($schemaFile));

        if (! is_object($schemaJson) || ! isset($schemaJson->items) || ! is_object($schemaJson->items)) {
            return null;
        }

        $items = $schemaJson->items;

        /** @psalm-suppress MixedPropertyFetch */
        if (isset($items->{'$ref'}) && is_string($items->{'$ref'})) {
            return pathinfo($items->{'$ref'}, PATHINFO_FILENAME);
        }

        /** @psalm-suppress MixedPropertyFetch */
        if (isset($items->type) && is_string($items->type)) {
            return $items->type;
        }

        return null;
    }

    /**
     * @param array<array{rel: string, src: string}>  $embeds
     * @param array<array{rel: string, href: string}> $links
     */
    private function addObjectRelations(string $objectName, array $embeds, array $links): void
    {
        if (! isset($this->objectRelations[$objectName])) {
            $this->objectRelations[$objectName] = ['embeds' => [], 'links' => []];
        }

        foreach ($embeds as $embed) {
            $target = $this->extractTargetFromUri($embed['src']);
            $this->objectRelations[$objectName]['embeds'][] = [
                'rel' => $embed['rel'],
                'target' => $target,
            ];
        }

        foreach ($links as $link) {
            $target = $this->extractTargetFromUri($link['href']);
            $this->objectRelations[$objectName]['links'][] = [
                'rel' => $link['rel'],
                'target' => $target,
            ];
        }
    }

    private function extractTargetFromUri(string $uri): string
    {
        preg_match('/^\/([a-z_-]+)/i', $uri, $matches);

        return isset($matches[1]) ? ucfirst($matches[1]) : ucfirst($uri);
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'boolean' => 'bool',
            default => $type,
        };
    }
}
