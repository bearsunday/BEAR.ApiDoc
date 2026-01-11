<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\Link;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

use function array_keys;
use function class_exists;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function glob;
use function implode;
use function in_array;
use function interface_exists;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strlen;
use function strtoupper;
use function substr;
use function trim;
use function ucfirst;

use const PATHINFO_FILENAME;
use const PREG_SET_ORDER;

final class AppDocGenerator
{
    /** @var array<string, array{uri: string, methods: array<string, array{params: string, links: string}>}> */
    private array $resources = [];

    /** @var array<string, array{schema: string, properties: string}> */
    private array $responses = [];

    /** @var array<string, string> */
    private array $sqlFiles = [];

    /** @var array<string, string> */
    private array $queryInterfaces = [];

    /** @var array<string, string> */
    private array $entities = [];

    private string $appDir = '';

    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function generate(): string
    {
        $this->detectAppDir();
        $this->collectResources();
        $this->collectResponses();
        $this->collectQueryInterfaces();
        $this->collectSqlFiles();
        $this->collectEntities();

        return (new AppDocRenderer())->render(
            $this->config->appName,
            $this->config->description,
            $this->resources,
            $this->responses,
            $this->queryInterfaces,
            $this->sqlFiles,
            $this->entities,
            $this->config->routes,
        );
    }

    private function detectAppDir(): void
    {
        // Detect app directory from appName namespace
        // FakeVendor\FakeProject -> find the directory containing src/Resource
        $parts = explode('\\', $this->config->appName);
        if (count($parts) >= 2) {
            // Try to find via composer autoload
            foreach ($this->config->resourceFiles as $meta) {
                $classFile = (new ReflectionClass($meta->class))->getFileName();
                if ($classFile !== false) {
                    // Go up from src/Resource/App/... to app root
                    $this->appDir = (string) preg_replace('#/src/Resource/.*$#', '', $classFile);

                    break;
                }
            }
        }
    }

    private function collectResources(): void
    {
        foreach ($this->config->resourceFiles as $meta) {
            /** @var ReflectionClass<object> $class */
            $class = new ReflectionClass($meta->class);
            $className = $this->getResourceClassName($meta->class);
            $uri = $meta->uriPath;

            $this->resources[$className] = [
                'uri' => $uri,
                'methods' => $this->collectMethods($class),
            ];
        }
    }

    /** @codeCoverageIgnore Response schema collection depends on runtime schema availability */
    private function collectResponses(): void
    {
        $schemaDir = $this->config->responseSchemaDir;
        if ($schemaDir === '' || ! is_dir($schemaDir)) {
            return;
        }

        foreach ($this->responses as $name => $data) {
            $schemaFile = $schemaDir . '/' . $data['schema'];
            if (! file_exists($schemaFile)) {
                continue;
            }

            $properties = $this->parseJsonSchemaProperties($schemaFile);
            $this->responses[$name]['properties'] = $properties;
        }
    }

    /** @codeCoverageIgnore Response schema parsing depends on runtime schema availability */
    private function parseJsonSchemaProperties(string $schemaFile): string
    {
        $content = file_get_contents($schemaFile);
        if ($content === false) {
            return '';
        }

        $schema = json_decode($content, true);
        if (! is_array($schema)) {
            return '';
        }

        // Handle array type
        if (isset($schema['type']) && $schema['type'] === 'array') {
            if (isset($schema['items']) && is_array($schema['items']) && isset($schema['items']['$ref']) && is_string($schema['items']['$ref'])) {
                $ref = pathinfo($schema['items']['$ref'], PATHINFO_FILENAME);

                return sprintf('(array of %s)', ucfirst($ref));
            }

            return '(array)';
        }

        // Handle object type
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            return implode(', ', array_keys($schema['properties']));
        }

        return '';
    }

    private function collectQueryInterfaces(): void
    {
        // Use query classes from config if available (from DI container)
        if ($this->config->queryClasses !== []) {
            // @codeCoverageIgnoreStart
            foreach ($this->config->queryClasses as $class) {
                $this->collectQueryInterfaceFromClass($class);
            }

            return;
            // @codeCoverageIgnoreEnd
        }

        // @codeCoverageIgnoreStart Fallback to filesystem scanning
        $queryDir = $this->appDir . '/src/Query';
        if (! is_dir($queryDir)) {
            return;
        }

        $files = glob($queryDir . '/*Interface.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $interfaceName = pathinfo($file, PATHINFO_FILENAME);

            $methods = $this->parseInterfaceMethods($content);
            if ($methods !== '') {
                $this->queryInterfaces[$interfaceName] = $methods;
            }
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param class-string $class
     *
     * @codeCoverageIgnore Only used when Ray.MediaQuery DI bindings are available
     */
    private function collectQueryInterfaceFromClass(string $class): void
    {
        if (! interface_exists($class)) {
            return;
        }

        $refClass = new ReflectionClass($class);
        $interfaceName = $refClass->getShortName();
        $methods = [];

        foreach ($refClass->getMethods() as $method) {
            $params = $this->simplifyReflectionParams($method->getParameters());
            $returnType = $this->getMethodReturnTypeName($method);
            $methods[] = sprintf('%s(%s):%s', $method->getName(), $params, $returnType);
        }

        if ($methods !== []) {
            $this->queryInterfaces[$interfaceName] = implode(', ', $methods);
        }
    }

    /**
     * @param array<ReflectionParameter> $params
     *
     * @codeCoverageIgnore Only used when Ray.MediaQuery DI bindings are available
     */
    private function simplifyReflectionParams(array $params): string
    {
        $names = [];
        foreach ($params as $param) {
            $names[] = $param->getName();
        }

        return implode(', ', $names);
    }

    /** @codeCoverageIgnore Only used when Ray.MediaQuery DI bindings are available */
    private function getMethodReturnTypeName(ReflectionMethod $method): string
    {
        $returnType = $method->getReturnType();
        if ($returnType === null) {
            return 'mixed';
        }

        if ($returnType instanceof \ReflectionNamedType) {
            $name = $returnType->getName();
            // Get short name for class types
            if (class_exists($name) || interface_exists($name)) {
                return (new ReflectionClass($name))->getShortName();
            }

            return $name;
        }

        return 'mixed';
    }

    /** @codeCoverageIgnore Only used in filesystem scanning fallback */
    private function parseInterfaceMethods(string $content): string
    {
        $methods = [];
        // Match: public function methodName(params): ReturnType;
        preg_match_all('/public\s+function\s+(\w+)\s*\(([^)]*)\)\s*:\s*(\w+)/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $methodName = $match[1];
            $params = $this->simplifyParams($match[2]);
            $returnType = $match[3];

            $methods[] = sprintf('%s(%s):%s', $methodName, $params, $returnType);
        }

        return implode(', ', $methods);
    }

    /** @codeCoverageIgnore Only used in filesystem scanning fallback */
    private function simplifyParams(string $params): string
    {
        // "string $id, int $limit = 10" -> "id, limit"
        $simplified = [];
        $parts = explode(',', $params);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/\$(\w+)/', $part, $m)) {
                $simplified[] = $m[1];
            }
        }

        return implode(', ', $simplified);
    }

    /** @codeCoverageIgnore SQL file collection depends on runtime directory availability */
    private function collectSqlFiles(): void
    {
        // Use sqlDir from config (from DI container) if available
        $sqlDir = $this->config->sqlDir !== '' ? $this->config->sqlDir : $this->appDir . '/var/sql';
        if (! is_dir($sqlDir)) {
            return;
        }

        $files = glob($sqlDir . '/*.sql');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME) . '.sql';
            $content = trim((string) file_get_contents($file));
            // Truncate long queries
            if (strlen($content) > 80) {
                $content = substr($content, 0, 77) . '...';
            }

            $this->sqlFiles[$fileName] = $content;
        }
    }

    /** @codeCoverageIgnore Entity collection depends on runtime directory availability */
    private function collectEntities(): void
    {
        $entityDir = $this->appDir . '/src/Entity';
        if (! is_dir($entityDir)) {
            return;
        }

        $files = glob($entityDir . '/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $entityName = pathinfo($file, PATHINFO_FILENAME);

            $properties = $this->parseEntityProperties($content);
            if ($properties !== '') {
                $this->entities[$entityName] = $properties;
            }
        }
    }

    /** @codeCoverageIgnore Entity property parsing depends on runtime file availability */
    private function parseEntityProperties(string $content): string
    {
        $properties = [];

        // Match constructor promoted properties: public readonly string $name
        preg_match_all('/(?:public|private|protected)\s+(?:readonly\s+)?(?:\??\w+)\s+\$(\w+)/m', $content, $matches);

        foreach ($matches[1] as $prop) {
            $properties[] = $prop;
        }

        return implode(', ', $properties);
    }

    private function getResourceClassName(string $fqcn): string
    {
        // MyVendor\MyApp\Resource\App\Users -> App/Users
        $parts = explode('\\Resource\\', $fqcn);
        if (count($parts) === 2) {
            return str_replace('\\', '/', $parts[1]);
        }

        return $fqcn; // @codeCoverageIgnore
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return array<string, array{params: string, links: string}>
     */
    private function collectMethods(ReflectionClass $class): array
    {
        $methods = [];
        $httpMethods = ['onGet', 'onPost', 'onPut', 'onPatch', 'onDelete'];

        foreach ($class->getMethods() as $method) {
            if (! in_array($method->getName(), $httpMethods, true)) {
                continue;
            }

            $httpMethod = strtoupper(substr($method->getName(), 2));
            $methods[$httpMethod] = [
                'params' => $this->collectParams($method),
                'links' => $this->collectLinks($method),
            ];

            // Track response schema for later collection
            $this->trackResponseSchema($method);
        }

        return $methods;
    }

    private function trackResponseSchema(ReflectionMethod $method): void
    {
        $attributes = $method->getAttributes(\BEAR\Resource\Annotation\JsonSchema::class);
        if (! isset($attributes[0])) {
            return;
        }

        $schema = $attributes[0]->newInstance();
        if ($schema->schema === '') {
            return;
        }

        $schemaName = pathinfo($schema->schema, PATHINFO_FILENAME);
        $responseName = ucfirst($schemaName);

        if (isset($this->responses[$responseName])) {
            return; // @codeCoverageIgnore
        }

        $this->responses[$responseName] = [
            'schema' => $schema->schema,
            'properties' => '', // Will be filled by collectResponses
        ];
    }

    private function collectParams(ReflectionMethod $method): string
    {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $params[] = $this->formatParam($param);
        }

        return implode(', ', $params);
    }

    private function formatParam(ReflectionParameter $param): string
    {
        $name = $param->getName();
        $required = ! $param->isOptional();

        return $required ? $name . '*' : $name;
    }

    private function collectLinks(ReflectionMethod $method): string
    {
        $links = [];

        // Collect href (Link)
        $linkAttrs = $method->getAttributes(Link::class);
        foreach ($linkAttrs as $attr) {
            $link = $attr->newInstance();
            $links[] = sprintf('href(%s)', $link->rel);
        }

        // Collect src (Embed)
        $embedAttrs = $method->getAttributes(Embed::class);
        foreach ($embedAttrs as $attr) {
            $embed = $attr->newInstance();
            $links[] = sprintf('src(%s)', $embed->rel);
        }

        return $links !== [] ? implode(', ', $links) : '-';
    }
}
