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

use function dirname;
use function file_get_contents;
use function in_array;
use function is_file;
use function is_numeric;
use function is_object;
use function is_string;
use function json_decode;
use function lcfirst;
use function pathinfo;
use function sprintf;
use function str_starts_with;
use function strtoupper;
use function substr;
use function ucfirst;

use const PATHINFO_FILENAME;

/**
 * Generates single-page HTML API documentation
 *
 * @psalm-import-type HtmlParam from Types
 * @psalm-import-type HtmlMethod from Types
 * @psalm-import-type HtmlObject from Types
 * @psalm-import-type HtmlRelation from Types
 * @psalm-import-type HtmlObjectRelations from Types
 * @psalm-import-type DocLink from Types
 */
final class HtmlGenerator
{
    /** @var array<string, array<string, HtmlMethod>> */
    private array $endpoints = [];

    /** @var array<string, HtmlObject> */
    private array $objects = [];

    /** @var HtmlObjectRelations */
    private array $objectRelations = [];

    private readonly HtmlRenderer $renderer;

    /** @var ArrayObject<string, string> */
    private readonly ArrayObject $semanticDictionary;

    /**
     * @param ArrayObject<string, string>|null $semanticDictionary
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    public function __construct(
        private readonly Config $config,
        private readonly string $requestSchemaDir,
        private readonly string $responseSchemaDir,
        ?ArrayObject $semanticDictionary = null,
        private readonly bool $inlineCss = false,
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

        $links = $this->extractLinks();

        return $this->renderer->render(
            $this->config->title,
            $this->config->description,
            $this->endpoints,
            $this->objects,
            $this->objectRelations,
            $links,
            $this->extractAlpsHtmlPath($links),
            $this->loadLocalCss(),
        );
    }

    private function loadLocalCss(): ?string
    {
        if (! $this->inlineCss) {
            return null;
        }

        return (string) file_get_contents(dirname(__DIR__) . '/docs/apidoc.css');
    }

    /** @param array<DocLink> $links */
    private function extractAlpsHtmlPath(array $links): string
    {
        foreach ($links as $link) {
            if ($link['rel'] === 'profile') {
                return $link['href'];
            }
        }

        return 'alps.html';
    }

    /**
     * @param ReflectionClass<T> $class
     *
     * @template T of object
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
        [$requestSchema, $responseSchemaName, $responseSchemaFile] = $this->extractJsonSchema($method);
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
            'responseSchemaFile' => $responseSchemaFile,
            'embeds' => $embeds,
            'links' => $links,
            'alps' => $alpsIds,
        ];
    }

    /** @return array<string> */
    private function extractAlpsIds(ReflectionMethod $method): array
    {
        $ids = [];
        foreach ($method->getAttributes(Alps::class) as $attr) {
            $alps = $attr->newInstance();
            $ids[] = $alps->id;
        }

        return $ids;
    }

    /** @return array{string, string, array<string, string>} */
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

    /** @return array{Schema|null, string|null, string|null} */
    private function extractJsonSchema(ReflectionMethod $method): array
    {
        $requestSchema = null;
        $responseSchemaName = null;
        $responseSchemaFile = null;
        $attributes = $method->getAttributes(JsonSchema::class);

        if ($attributes === []) {
            return [$requestSchema, $responseSchemaName, $responseSchemaFile];
        }

        $schemaAttr = $attributes[0]->newInstance();
        if ($schemaAttr->params !== '') {
            $requestSchema = $this->loadSchema($this->requestSchemaDir, $schemaAttr->params);
        }

        if ($schemaAttr->schema !== '') {
            $responseSchema = $this->loadSchema($this->responseSchemaDir, $schemaAttr->schema);
            if ($responseSchema instanceof \BEAR\ApiDoc\Schema) {
                $responseSchemaName = $responseSchema->title ?: ucfirst(pathinfo($schemaAttr->schema, PATHINFO_FILENAME));
                $responseSchemaFile = $schemaAttr->schema;
                $this->addObject($responseSchemaName, $responseSchema, $responseSchemaFile);
            }
        }

        return [$requestSchema, $responseSchemaName, $responseSchemaFile];
    }

    /** @return array{array<array{rel: string, src: string}>, array<array{rel: string, href: string, title: string}>} */
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
            $links[] = ['rel' => $link->rel, 'href' => $link->href, 'title' => $link->title];
        }

        return [$embeds, $links];
    }

    /**
     * @param array<string, string> $paramDescriptions
     *
     * @return array<HtmlParam>
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

            if ($requestSchema instanceof \BEAR\ApiDoc\Schema && isset($requestSchema->props[$paramName])) {
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

    /** @SuppressWarnings("PHPMD.NPathComplexity") */
    private function addObject(string $name, Schema $schema, ?string $schemaFile = null): void
    {
        if (isset($this->objects[$name])) {
            return;
        }

        $properties = [];
        $arrayItemType = null;

        if ($schema->type === 'array') {
            $arrayItemType = $this->getArrayItemType($schema);
        }

        // Load raw schema to access definitions
        /** @psalm-suppress MixedAssignment */
        $schemaJson = json_decode((string) file_get_contents($schema->file->getPathname()));

        foreach ($schema->props as $propName => $prop) {
            if ($propName === '_links') {
                continue;
            }

            if ($propName === '_embedded') {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $formatValue = $prop->constraints->constrains['format'] ?? null;
            $format = is_string($formatValue) ? $formatValue : null;
            $constraints = $prop->constraints->constrains;
            unset($constraints['format']);

            // Check for nested object with properties
            $ref = null;
            if ($prop->type === 'object' && isset($constraints['properties']) && is_object($constraints['properties'])) {
                $nestedName = $name . '.' . ucfirst($propName);
                $this->addNestedObject($nestedName, $constraints['properties'], $schemaFile);
                $ref = $nestedName;
                unset($constraints['properties']);
            }

            // Check for $ref to #/definitions/
            if ($prop->type === 'object' && isset($constraints['$ref']) && is_string($constraints['$ref'])) {
                $refPath = $constraints['$ref'];
                if (str_starts_with($refPath, '#/definitions/')) {
                    $defName = ucfirst(substr($refPath, 14)); // Remove '#/definitions/'
                    $ref = $defName;
                    // Add the definition as an Object if it exists
                    /** @psalm-suppress MixedPropertyFetch */
                    if (is_object($schemaJson) && isset($schemaJson->definitions->{lcfirst($defName)}) && is_object($schemaJson->definitions->{lcfirst($defName)})) {
                        /** @psalm-suppress MixedPropertyFetch, MixedAssignment */
                        $definition = $schemaJson->definitions->{lcfirst($defName)};
                        /** @psalm-suppress MixedPropertyFetch, MixedArgument */
                        if (isset($definition->properties) && is_object($definition->properties)) {
                            $this->addNestedObject($defName, $definition->properties, $schemaFile);
                        }
                    }
                }

                unset($constraints['$ref']);
            }

            $properties[] = [
                'name' => $propName,
                'type' => $prop->type,
                'description' => $prop->description,
                'example' => $prop->example,
                'format' => $format,
                'constraints' => $constraints,
                'ref' => $ref,
            ];
        }

        $this->objects[$name] = [
            'name' => $name,
            'properties' => $properties,
            'arrayItemType' => $arrayItemType,
            'schemaFile' => $schemaFile,
        ];
    }

    private function addNestedObject(string $name, object $nestedProperties, ?string $schemaFile = null): void
    {
        if (isset($this->objects[$name])) {
            return;
        }

        $properties = [];
        /** @var array<string, mixed> $propsArray */
        $propsArray = (array) $nestedProperties;
        foreach ($propsArray as $propName => $prop) {
            if (! is_object($prop)) {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $type = $prop->type ?? 'mixed';
            /** @psalm-suppress MixedAssignment */
            $description = $prop->description ?? '';
            /** @psalm-suppress MixedAssignment */
            $example = $prop->example ?? null;
            /** @psalm-suppress MixedAssignment */
            $format = $prop->format ?? null;

            $properties[] = [
                'name' => $propName,
                'type' => is_string($type) ? $type : 'mixed',
                'description' => is_string($description) ? $description : '',
                'example' => is_string($example) || is_numeric($example) ? (string) $example : null,
                'format' => is_string($format) ? $format : null,
                'constraints' => [],
                'ref' => null,
            ];
        }

        $this->objects[$name] = [
            'name' => $name,
            'properties' => $properties,
            'arrayItemType' => null,
            'schemaFile' => $schemaFile,
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
     * @param array<array{rel: string, src: string}>                 $embeds
     * @param array<array{rel: string, href: string, title: string}> $links
     */
    private function addObjectRelations(string $objectName, array $embeds, array $links): void
    {
        if (! isset($this->objectRelations[$objectName])) {
            $this->objectRelations[$objectName] = ['embeds' => [], 'links' => []];
        }

        foreach ($embeds as $embed) {
            // Embed has no title attribute, use semantic dictionary only
            $title = $this->semanticDictionary[$embed['rel']] ?? '';
            $this->objectRelations[$objectName]['embeds'][] = [
                'rel' => $embed['rel'],
                'href' => $embed['src'],
                'title' => $title,
            ];
        }

        foreach ($links as $link) {
            // Priority: Link annotation title > semantic dictionary
            $title = $link['title'] !== '' ? $link['title'] : ($this->semanticDictionary[$link['rel']] ?? '');
            $this->objectRelations[$objectName]['links'][] = [
                'rel' => $link['rel'],
                'href' => $link['href'],
                'title' => $title,
            ];
        }
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'boolean' => 'bool',
            default => $type,
        };
    }

    /** @return array<DocLink> */
    private function extractLinks(): array
    {
        $links = [];
        foreach ($this->config->links as $link) {
            $rel = (string) ($link['rel'] ?? '');
            $href = (string) ($link['href'] ?? '');
            if ($rel !== '' && $href !== '') {
                $links[] = ['rel' => $rel, 'href' => $href];
            }
        }

        return $links;
    }
}
