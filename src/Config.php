<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
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
use function is_iterable;
use function is_string;
use function property_exists;
use function realpath;
use function sprintf;

class Config
{
    /**
     * @var string
     * @readonly
     */
    public $appName;

    /**
     * @var string
     * @readonly
     */
    public $scheme;

    /**
     * @var string
     * @readonly
     */
    public $docDir;

    /**
     * @var string
     * @readonly
     */
    public $format;

    /**
     * @var string
     * @readonly
     */
    public $title;

    /**
     * @var string
     * @readonly
     */
    public $description;

    /**
     * @var array<SimpleXMLElement>
     * @readonly
     */
    public $links;

    /**
     * @var string
     * @readonly
     */
    public $alps = '';

    /**
     * @var Generator<ResMeta>
     * @readonly
     */
    public $resourceFiles;

    /**
     * @var ArrayObject<string, string>
     * @readonly
     */
    public $modelRepository;

    /**
     * @var array<string, string>
     * @readonly
     */
    public $routes = [];

    /**
     * @var string
     * @readonly
     */
    public $requestSchemaDir = '';

    /**
     * @var string
     * @readonly
     */
    public $responseSchemaDir = '';

    /**
     * @psalm-suppress
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(string $configFile)
    {
        $xml = (new XmlLoader())($configFile, dirname(__DIR__) . '/apidoc.xsd');
        assert(property_exists($xml, 'appName'));
        assert(property_exists($xml, 'docDir'));
        assert(property_exists($xml, 'format'));
        assert(property_exists($xml, 'scheme'));
        $dir = realpath(dirname($configFile));
        $this->appName = (string) $xml->appName;
        $this->docDir = sprintf('%s/%s', $dir, (string) $xml->docDir);
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
            $jsonSchemaDir = $injector->getInstance('', 'json_schema_dir');
            assert(is_string($jsonSchemaDir));
            $this->responseSchemaDir = $jsonSchemaDir;
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
        }

        try {
            $jsonValidateDir = $injector->getInstance('', 'json_validate_dir');
            assert(is_string($jsonValidateDir));
            $this->requestSchemaDir = $jsonValidateDir;
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
        }

        $this->modelRepository = new ModelRepository();
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
            $routerContainer = $injector->getInstance(RouterContainer::class);

            return $routerContainer->getMap();
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
            return null;
        }
    }
}
