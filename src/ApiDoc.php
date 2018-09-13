<?php
namespace BEAR\ApiDoc;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Map;
use Aura\Router\RouterContainer;
use BEAR\Resource\Exception\HrefNotFoundException;
use BEAR\Resource\Exception\ResourceNotFoundException;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use LogicException;
use manuelodelain\Twig\Extension\LinkifyExtension;
use Ray\Di\Di\Inject;
use Ray\Di\Di\Named;
use Twig_Extension_Debug;
use function explode;
use function file_get_contents;
use function get_class;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_replace;

class ApiDoc extends ResourceObject
{
    /**
     * @var ResourceInterface
     */
    private $resource;

    /**
     * Optional aura router
     *
     * @var RouterContainer|null
     */
    private $route;

    /**
     * @var string
     */
    private $schemaDir;

    /**
     * @var string|null
     */
    private $routerFile;

    /**
     * @var Map|array
     */
    private $map;

    /**
     * @var array
     */
    private $template = [];

    /**
     * @var string
     */
    private $appName;

    /**
     * @Named("schemaDir=json_schema_dir,routerFile=aura_router_file")
     */
    public function __construct(
        ResourceInterface $resource,
        string $schemaDir,
        Template $template,
        RouterContainer $routerContainer = null,
        string $routerFile = null
    ) {
        $this->resource = $resource;
        $this->route = $routerContainer;
        $this->schemaDir = $schemaDir;
        $this->routerFile = $routerFile;
        $this->map = $this->route instanceof RouterContainer ? $this->route->getMap() : [];
        $this->template = [
            'index' => $template->index,
            'base.html.twig' => $template->base,
            'home.html.twig' => $template->home,
            'rel.html.twig' => $template->rel,
            'schema.table.html.twig' => $template->shcemaTable
        ];
        $index = $this->resource->get('app://self/index');
        $names = explode('\\', get_class($index));
        $this->appName = sprintf('%s\%s', $names[0], $names[1]);
    }

    /**
     * @Inject
     */
    public function setRenderer(RenderInterface $renderer)
    {
        unset($renderer);
        $this->renderer = new class($this->template) implements RenderInterface {
            private $template;

            public function __construct(array $template)
            {
                $this->template = $template;
            }

            public function render(ResourceObject $ro)
            {
                $ro->headers['content-type'] = 'text/html; charset=utf-8';
                $twig = new \Twig_Environment(new \Twig_Loader_Array($this->template), ['debug' => true]);
                $twig->addExtension(new Twig_Extension_Debug);
                $twig->addExtension(new RefLinkExtention);
                $twig->addExtension(new LinkifyExtension);
                $ro->view = $twig->render('index', $ro->body);

                return $ro->view;
            }
        };

        return $this;
    }

    public function onGet(string $rel = null, $schema = null) : ResourceObject
    {
        if ($rel) {
            return $this->relPage($rel);
        }
        if ($schema) {
            return $this->schemaPage($schema);
        }

        return $this->indexPage();
    }

    public function transfer(TransferInterface $responder, array $server)
    {
        if (! $responder instanceof FileResponder) {
            return parent::transfer($responder, $server); // @codeCoverageIgnore
        }
        $this->indexPage();
        $responder->set((string) $this->indexPage(), $this->schemaDir);

        return parent::transfer($responder, $server);
    }

    private function indexPage() : ResourceObject
    {
        $index = $this->resource->get('app://self/index')->body;
        $curies = new Curies($index['_links']['curies']);
        $links = [];
        unset($index['_links']['curies'], $index['_links']['self']);
        foreach ($index['_links'] as $nameRel => $value) {
            $rel = str_replace($curies->name . ':', '', $nameRel);
            $links[$rel] = new Curie($nameRel, $value, $curies);
        }
        unset($index['_links']);
        $schemas = $this->getSchemas();
        $this->body = [
            'app_name' => $this->appName,
            'name' => $curies->name,
            'messages' => $index,
            'links' => $links,
            'schemas' => $schemas
        ];

        return $this;
    }

    private function schemaPage(string $id) : ResourceObject
    {
        $path = realpath($this->schemaDir . '/' . $id);
        $isInvalidFilePath = (strncmp($path, $this->schemaDir, strlen($this->schemaDir)) !== 0);
        if ($isInvalidFilePath) {
            throw new \DomainException($id);
        }
        $schema = (array) json_decode(file_get_contents($path), true);
        $this->body = [
            'app_name' => $this->appName,
            'schema' => $schema
        ];

        return $this;
    }

    private function getSchemas() : array
    {
        $schemas = [];
        foreach (glob($this->schemaDir . '/*.json') as $json) {
            $schemas[] = new JsonSchema(file_get_contents($json), $json);
        }

        return $schemas;
    }

    private function relPage(string $rel) : ResourceObject
    {
        $index = $this->resource->options('app://self/')->body;
        $namedRel = sprintf('%s:%s', $index['_links']['curies']['name'], $rel);
        $links = $index['_links'];
        if (! isset($links[$namedRel]['href'])) {
            throw new ResourceNotFoundException($rel);
        }
        $href = $links[$namedRel]['href'];
        $isTemplated = $this->isTemplated($links[$namedRel]);
        $path = $isTemplated ? $this->match($href) : $href;
        $uri = "app://self{$path}";
        try {
            $optionsJson = $this->resource->options($uri)->view;
        } catch (ResourceNotFoundException $e) {
            throw new HrefNotFoundException($href, 0, $e);
        }
        if ($optionsJson === null) {
            throw new LogicException('No option view'); // @codeCoverageIgnore
        }
        $options = json_decode($optionsJson, true);
        foreach ($options as &$option) {
            if (isset($option['schema'])) {
                $option['meta'] = new JsonSchema(json_encode($option['schema']), $uri);
            }
        }
        unset($option);
        $this->body = [
            'app_name' => $this->appName,
            'doc' => $options,
            'rel' => $rel,
            'href' => $href
        ];

        return $this;
    }

    private function isTemplated(array $links) : bool
    {
        return isset($links['templated']) && $links['templated'] === true;
    }

    /**
     * @throws RouteNotFound
     */
    private function match($tempaltedPath) : string
    {
        foreach ($this->map as $route) {
            if ($tempaltedPath === $route->path) {
                return $route->name;
            }
        }

        throw new RouteNotFound($tempaltedPath);
    }
}
