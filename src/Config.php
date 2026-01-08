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
use Generator;
use Ray\Di\AbstractModule;
use Ray\Di\Exception\Unbound;
use Ray\Di\Injector;
use Ray\Di\InjectorInterface;
use SimpleXMLElement;

use function assert;
use function class_exists;
use function dirname;
use function in_array;
use function is_iterable;
use function is_string;
use function property_exists;
use function realpath;
use function sprintf;

final class Config
{
    public readonly string $appName;
    public readonly string $scheme;
    public readonly string $docDir;
    public readonly string $format;
    public readonly string $title;
    public readonly string $description;

    /** @var list<SimpleXMLElement> */
    public readonly array $links;

    public string $alps = '';

    /** @var Generator<ResMeta> */
    public readonly Generator $resourceFiles;

    /** @var ArrayObject<string, string> */
    public readonly ArrayObject $modelRepository;

    /** @var array<string, string> */
    public array $routes = [];

    public string $requestSchemaDir = '';
    public string $responseSchemaDir = '';

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
        assert($dir !== false);
        $appName = (string) $xml->appName;
        assert($appName !== '');
        $this->appName = $appName;
        $this->docDir = sprintf('%s/%s', $dir, (string) $xml->docDir);
        $this->format = (string) $xml->format;
        $scheme = (string) $xml->scheme;
        assert(in_array($scheme, ['*', 'app', 'page'], true));
        $this->scheme = $scheme;

        $this->description = property_exists($xml, 'description') ? (string) $xml->description : '';
        $this->title = property_exists($xml, 'title') ? (string) $xml->title : '';
        $alps = property_exists($xml, 'alps') ? (string) $xml->alps : '';
        if ($alps) {
            $this->alps = sprintf('%s/%s', $dir, $alps);
        }

        /** @var list<\SimpleXMLElement> $links */
        $links = [];
        if (property_exists($xml, 'links') && $xml->links instanceof \SimpleXMLElement) {
            $linkElements = $xml->links->children();
            if ($linkElements !== null) {
                /** @var \SimpleXMLElement $link */
                foreach ($linkElements as $link) {
                    if ($link->getName() === 'link') {
                        $links[] = $link;
                    }
                }
            }
        }

        $this->links = $links;

        /** @var class-string<AbstractModule> $appModuleClass */
        $appModuleClass = sprintf('%s\\Module\\AppModule', $this->appName);
        if (! class_exists($appModuleClass)) {
            throw new InvalidAppNamespaceException($this->appName);
        }

        $meta = new Meta($this->appName);

        /** @psalm-suppress UnsafeInstantiation */
        $appModule = new $appModuleClass($meta, new AppMetaModule($meta));
        /** @psalm-suppress all */
        $injector = new Injector($appModule);
        $this->resourceFiles = $meta->getGenerator($this->scheme);

        try {
            $jsonSchemaDir = $injector->getInstance('', 'json_schema_dir');
            assert(is_string($jsonSchemaDir));
            $this->responseSchemaDir = $jsonSchemaDir;
            // @codeCoverageIgnoreStart
        } catch (Unbound) {
        }

        try {
            $jsonValidateDir = $injector->getInstance('', 'json_validate_dir');
            assert(is_string($jsonValidateDir));
            $this->requestSchemaDir = $jsonValidateDir;
            // @codeCoverageIgnoreStart
        } catch (Unbound) {
        }

        $this->modelRepository = new ModelRepository();
        $map = $this->getRouterMap($injector);
        // @codeCoverageIgnoreStart
        if (! is_iterable($map)) {
            return;
        }

        /** @var Route $route */
        foreach ($map as $route) {
            // @codeCoverageIgnoreEnd
            $this->routes[$route->name] = $route->path;
        }
    }

    /**
     * @psalm-return Map
     * @phpstan-return Map<string, Route>
     */
    private function getRouterMap(InjectorInterface $injector): ?Map
    {
        try {
            $routerContainer = $injector->getInstance(RouterContainer::class);

            return $routerContainer->getMap();
            // @codeCoverageIgnoreStart
        } catch (Unbound) {
            return null;
        }
    }
}
