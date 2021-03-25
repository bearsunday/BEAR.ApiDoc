<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use Aura\Router\Map;
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

use function array_unique;
use function assert;
use function class_exists;
use function copy;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function substr;

/**
 * @psalm-pure
 */

final class DocApp
{
    private $meta;

    /** @var DocClass */
    private $docClass;

    /** @var array */
    private $routes;

    /** @var string */
    private $responseSchemaDir;

    /** @var ArrayObject<string> */
    private $modelRepository;

    public function __construct(string $appName)
    {
        $appModule = sprintf('%s\\Module\\AppModule', $appName);
        assert(class_exists($appModule));
        $this->meta = new Meta($appName);
        $injector = new Injector(new $appModule($this->meta, new Module\AppMetaModule($this->meta)));
        $reader = new DualReader(new AnnotationReader(), new AttributeReader());
        $this->responseSchemaDir = $injector->getInstance('', 'json_schema_dir');
        $requestSchemaDir = $injector->getInstance('', 'json_validate_dir');
        $this->modelRepository = new ArrayObject();
        $this->docClass = new DocClass($reader, $requestSchemaDir, $this->responseSchemaDir, $this->modelRepository);
        $map = $this->getRouterMap($injector);
        foreach ($map as $route) {
            $this->routes[$route->name] = $route->path;
        }
    }

    public function __invoke(string $docDir, string $scheme): void
    {
        $generator = $this->meta->getGenerator($scheme);
        foreach ($generator as $meta) {
            $path = $this->routes[$meta->uriPath] ?? $meta->uriPath;
            $classView = ($this->docClass)($path, new ReflectionClass($meta->class));
            $file = sprintf('%s/%s.md', $docDir, substr($meta->uriPath, 1));
            file_put_contents($file, $classView);
        }

        $outputDir = sprintf('%s/schema', $docDir);
        $models = array_unique((array) $this->modelRepository);
        ! is_dir($outputDir) && mkdir($outputDir);
        $this->copySchema($this->responseSchemaDir, $outputDir);
    }

    private function copySchema(string $inputDir, string $outputDir): void
    {
        foreach (new RecursiveDirectoryIterator($inputDir, FilesystemIterator::SKIP_DOTS) as $file) {
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

    private function getPath(string $path): string
    {
        foreach ($this->map as $route) {
            if ($route->name === $path) {
                return $route->path;
            }
        }

        return $path;
    }
}
