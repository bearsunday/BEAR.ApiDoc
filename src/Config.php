<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use BEAR\ApiDoc\Exception\ConfigNotFoundException;
use BEAR\ApiDoc\Exception\InvalidAppNamespaceException;
use BEAR\AppMeta\Meta;
use BEAR\AppMeta\ResMeta;
use Doctrine\Common\Annotations\Reader;
use Generator;
use Ray\Di\Exception\Unbound;
use Ray\Di\Injector;
use Ray\Di\InjectorInterface;
use SimpleXMLElement;

use function assert;
use function class_exists;
use function dirname;
use function file_exists;
use function getcwd;
use function is_dir;
use function is_iterable;
use function property_exists;
use function realpath;
use function simplexml_load_file;
use function sprintf;

class Config
{
    /** @var string */
    public $appName;

    /** @var string */
    public $scheme;

    /** @var string */
    public $docDir;

    /** @var string */
    public $format;

    /** @var string */
    public $title;

    /** @var string */
    public $description;

    /** @var array<SimpleXMLElement> */
    public $links;

    /** @var string */
    public $alps = '';

    /** @var Generator<ResMeta> */
    public $resourceFiles;

    /** @var ArrayObject<string, string> */
    public $modelRepository;

    /** @var array<string, string> */
    public $routes = [];

    /** @var string */
    public $requestSchemaDir = '';

    /** @var string */
    public $responseSchemaDir = '';

    /**
     * @psalm-suppress
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(string $configFile)
    {
        $configFile = $this->locateConfigFile($configFile);
        $xml = simplexml_load_file($configFile);
        assert($xml instanceof SimpleXMLElement);
        assert(property_exists($xml, 'appName'));
        assert(property_exists($xml, 'docDir'));
        assert(property_exists($xml, 'format'));
        assert(property_exists($xml, 'scheme'));
        $dir = realpath(dirname($configFile));
        $this->appName = (string) $xml->appName;
        $this->docDir = (string) (sprintf('%s/%s', $dir, (string) $xml->docDir));
        $this->format = (string) $xml->format;
        $this->scheme = (string) $xml->scheme;

        $this->description = property_exists($xml, 'description') ? (string) $xml->description : '';
        $this->title = property_exists($xml, 'title') ? (string) $xml->title : '';
        $alps = property_exists($xml, 'alps') ? (string) $xml->alps : '';
        if ($alps) {
            $this->alps = sprintf('%s/%s', $dir, $alps);
        }

        /** @var array<SimpleXMLElement> $links */
        $links = property_exists($xml, 'links') ? $xml->links : [];
        $this->links = $links;

        $appModule = sprintf('%s\\Module\\AppModule', $this->appName);
        if (! class_exists($appModule)) {
            throw new InvalidAppNamespaceException($this->appName);
        }

        $meta = new Meta($this->appName);

        /** @psalm-suppress all */
        $injector = new Injector(new $appModule($meta, new AppMetaModule($meta)));
        assert($injector instanceof InjectorInterface);
        $reader = $injector->getInstance(Reader::class);
        assert($reader instanceof Reader);
        $this->resourceFiles = $meta->getGenerator($this->scheme);

        try {
            $this->responseSchemaDir = (string) $injector->getInstance('', 'json_schema_dir');
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
        }

        try {
            $this->requestSchemaDir = (string) $injector->getInstance('', 'json_validate_dir');
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
        }

        /** @var ArrayObject<string, string> $modelRepository */
        $modelRepository = new ArrayObject();
        $this->modelRepository = $modelRepository;
        $map = $this->getRouterMap($injector);
        // @codeCoverageIgnoreStart
        if (! is_iterable($map)) {
            return;
        }

        foreach ($map as $route) {
            // @codeCoverageIgnoreEnd
            assert($route instanceof Route);
            $this->routes[$route->name] = $route->path;
        }
    }

    /**
     * @psalm-return Map
     * @phpstan-return Map<string, Route>
     */
    private function getRouterMap(InjectorInterface $injector): ?Map // @phpstan-ignore-line
    {
        try {
            /** @var RouterContainer */
            $routerContainer = $injector->getInstance(RouterContainer::class);

            return $routerContainer->getMap();
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
            return null;
        }
    }

    public function locateConfigFile(string $path): string
    {
        if (file_exists($path)) {
            return $path;
        }

        $maybePath = sprintf('%s/%s', getcwd(), $path);
        if (file_exists($maybePath) && ! is_dir($maybePath)) {
            return $maybePath;
        }

        $dirPath = realpath($path) ?: getcwd();
        if ($dirPath === false) {
            goto config_not_found;
        }

        if (! is_dir($dirPath)) { // @phpstan-ignore-line
            $dirPath = dirname($dirPath); // @phpstan-ignore-line
        }

        do {
            $maybePath = sprintf('%s/%s', $dirPath, 'apidoc.xml');
            if (file_exists($maybePath) || file_exists($maybePath .= '.dist')) {
                return $maybePath;
            }

            $dirPath = dirname($dirPath); // @phpstan-ignore-line
        } while (dirname($dirPath) !== $dirPath);

        config_not_found:

        throw new ConfigNotFoundException('Config not found for path ' . $path);
    }
}
