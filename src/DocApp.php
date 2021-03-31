<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use BEAR\AppMeta\Meta;
use BEAR\Package\Module;
use Doctrine\Common\Annotations\Reader;
use FilesystemIterator;
use Generator;
use Koriym\AppStateDiagram\AlpsProfile;
use Koriym\AppStateDiagram\SemanticDescriptor;
use Ray\Di\Exception\Unbound;
use Ray\Di\Injector;
use Ray\Di\InjectorInterface;
use RecursiveDirectoryIterator;
use ReflectionClass;
use SplFileInfo;

use function array_unique;
use function assert;
use function class_exists;
use function copy;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_iterable;
use function is_string;
use function mkdir;
use function sprintf;
use function substr;

/**
 * @psalm-pure
 */
final class DocApp
{
    /** @var Meta */
    private $meta;

    /** @var DocClass */
    private $docClass;

    /** @var array<string, string> */
    private $routes = [];

    /** @var string */
    private $responseSchemaDir;

    /** @var ArrayObject<string, string> */
    private $modelRepository;

    public function __construct(string $appName)
    {
        $appModule = sprintf('%s\\Module\\AppModule', $appName);
        assert(class_exists($appModule));
        $this->meta = new Meta($appName);
        /** @psalm-suppress all */
        $injector = new Injector(new $appModule($this->meta, new Module\AppMetaModule($this->meta)));
        assert($injector instanceof InjectorInterface);
        $reader = $injector->getInstance(Reader::class);
        /** @var string responseSchemaDir $responseSchemaDir */
        $responseSchemaDir = $injector->getInstance('', 'json_schema_dir');
        $this->responseSchemaDir = $responseSchemaDir;
        $requestSchemaDir = $injector->getInstance('', 'json_validate_dir');
        assert(is_string($requestSchemaDir));
        $this->modelRepository = new ArrayObject();
        $this->docClass = new DocClass($reader, $requestSchemaDir, $this->responseSchemaDir, $this->modelRepository);
        $map = $this->getRouterMap($injector);
        if (! is_iterable($map)) {
            return;
        }

        foreach ($map as $route) {
            assert($route instanceof Route);
            $this->routes[$route->name] = $route->path;
        }
    }

    public function dumpMarkDown(string $docDir, string $scheme, string $alpsFile = ''): void
    {
        $genMarkDown = $this->getGenMarkdown($docDir, $scheme, $alpsFile);
        foreach ($genMarkDown as $file => $markdown) {
            file_put_contents($file, $markdown);
        }
    }

    /**
     * @return Generator<string, string>
     */
    private function getGenMarkdown(string $docDir, string $scheme, string $alpsFile = ''): Generator
    {
        $semanticDictionary = $alpsFile ? $this->registerAlpsProfile($alpsFile) : new ArrayObject();
        $generator = $this->meta->getGenerator($scheme);
        $paths = [];
        foreach ($generator as $meta) {
            $path = $this->routes[$meta->uriPath] ?? $meta->uriPath;
            assert(class_exists($meta->class));
            $markdown = ($this->docClass)($path, new ReflectionClass($meta->class), $semanticDictionary);
            $file = sprintf('%s/%s.md', $docDir, substr($meta->uriPath, 1));
            $paths[$path] = substr($meta->uriPath, 1);

            yield $file => $markdown;
        }

        $this->copySchemas($docDir, $paths);
    }

    /**
     * @param array<string, string> $paths
     */
    private function copySchemas(string $docDir, array $paths): void
    {
        $outputDir = sprintf('%s/schema', $docDir);
        $objects = array_unique((array) $this->modelRepository);
        ! is_dir($outputDir) && ! mkdir($outputDir) && ! is_dir($outputDir);
        $index = (string) new Index($this->meta->name, '', $paths, $objects);
        file_put_contents(sprintf('%s/index.md', $docDir), $index);
        $this->copySchema($this->responseSchemaDir, $outputDir);
    }

    /**
     * @return ArrayObject<string, string>
     */
    private function registerAlpsProfile(string $file): ArrayObject
    {
        assert(file_exists($file));
        $alps = new AlpsProfile($file);
        $semanticDictionary = new ArrayObject();
        foreach ($alps->descriptors as $descriptor) {
            if ($descriptor instanceof SemanticDescriptor) {
                $semanticDictionary[$descriptor->id] = $this->getSematicTitle($descriptor);
            }
        }

        return $semanticDictionary;
    }

    private function getSematicTitle(SemanticDescriptor $descriptor): string
    {
        if ($descriptor->title) {
            return $descriptor->title;
        }

        if (isset($descriptor->doc->value)) {
            return $descriptor->doc->value;
        }

        if (isset($descriptor->def)) {
            return sprintf('[%s](%s)', $descriptor->def, $descriptor->def);
        }

        return '';
    }

    private function copySchema(string $inputDir, string $outputDir): void
    {
        foreach (new RecursiveDirectoryIterator($inputDir, FilesystemIterator::SKIP_DOTS) as $file) {
            assert($file instanceof SplFileInfo);
            copy((string) $file, sprintf('%s/%s', $outputDir, $file->getFilename()));
        }
    }

    /**
     * @phpstan-return Map<string, Route>
     * @psalm-return Map
     */
    private function getRouterMap(InjectorInterface $injector): ?Map // @phpstan-ignore-line
    {
        try {
            /** @var RouterContainer */
            $routerContainer = $injector->getInstance(RouterContainer::class);

            return $routerContainer->getMap();
        } catch (Unbound $e) {
            return null;
        }
    }
}
