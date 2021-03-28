<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use BEAR\AppMeta\Meta;
use BEAR\Package\Module;
use Doctrine\Common\Annotations\AnnotationReader;
use FilesystemIterator;
use Koriym\Attributes\AttributeReader;
use Koriym\Attributes\DualReader;
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

    /** @var ArrayObject<mixed, mixed> */
    private $modelRepository;

    public function __construct(string $appName)
    {
        $appModule = sprintf('%s\\Module\\AppModule', $appName);
        assert(class_exists($appModule));
        $this->meta = new Meta($appName);
        /** @psalm-suppress all */
        $injector = new Injector(new $appModule($this->meta, new Module\AppMetaModule($this->meta)));
        assert($injector instanceof InjectorInterface);
        $reader = new DualReader(new AnnotationReader(), new AttributeReader());
        /** @var string responseSchemaDir */
        $this->responseSchemaDir = $injector->getInstance('', 'json_schema_dir');
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

    public function __invoke(string $docDir, string $scheme): void
    {
        $generator = $this->meta->getGenerator($scheme);
        $paths = [];
        foreach ($generator as $meta) {
            $path = $this->routes[$meta->uriPath] ?? $meta->uriPath;
            $classView = ($this->docClass)($path, new ReflectionClass($meta->class));
            $file = sprintf('%s/%s.md', $docDir, substr($meta->uriPath, 1));
            file_put_contents($file, $classView);
            $paths[$path] = substr($meta->uriPath, 1);
        }

        $outputDir = sprintf('%s/schema', $docDir);
        $objects = array_unique((array) $this->modelRepository);
        ! is_dir($outputDir) && ! mkdir($outputDir) && ! is_dir($outputDir);
        $index = (string) new Index($this->meta->name, '', $paths, $objects);
        file_put_contents(sprintf('%s/index.md', $docDir), $index);
        $this->copySchema($this->responseSchemaDir, $outputDir);
    }

    private function copySchema(string $inputDir, string $outputDir): void
    {
        foreach (new RecursiveDirectoryIterator($inputDir, FilesystemIterator::SKIP_DOTS) as $file) {
            assert($file instanceof SplFileInfo);
            copy((string) $file, sprintf('%s/%s', $outputDir, $file->getFilename()));
        }
    }

    private function getRouterMap(InjectorInterface $injector): ?Map
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
