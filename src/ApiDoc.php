<?php
namespace BEAR\ApiDoc;

use Aura\Router\Map;
use Aura\Router\RouterContainer;
use BEAR\AppMeta\Meta;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use Koriym\Alps\AbstractAlps;
use LogicException;
use manuelodelain\Twig\Extension\LinkifyExtension;
use Ray\Di\Di\Inject;
use Ray\Di\Di\Named;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extensions\TextExtension;
use Twig\Loader\ArrayLoader;
use function array_keys;
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
     * @var string
     */
    private $ext;

    /**
     * @var AbstractAlps
     */
    private $alps;

    /**
     * @Named("schemaDir=json_schema_dir,routerContainer=router_container,routerFile=aura_router_file")
     */
    public function __construct(
        ResourceInterface $resource,
        string $schemaDir,
        AbstractTemplate $template,
        AbstractAlps $alps,
        $routerContainer,
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
            'uri.html.twig' => $template->uri,
            'rel.html.twig' => $template->rel,
            'allow.html.twig' => $template->allow,
            'request.html.twig' => $template->request,
            'embed.html.twig' => $template->embed,
            'link.html.twig' => $template->links,
            'definition.html.twig' => $template->definition,
            'schema.html.twig' => $template->shcemaTable,
        ];
        $this->ext = $template->ext;
        $index = $this->resource->get('app://self/index');
        $names = explode('\\', get_class($index));
        $this->appName = sprintf('%s\%s', $names[0], $names[1]);
        $this->alps = $alps;
    }

    /**
     * @Inject
     */
    public function setRenderer(RenderInterface $renderer)
    {
        unset($renderer);
        $this->renderer = new class($this->template, $this->alps, $this->map) implements RenderInterface {
            /**
             * @var array
             */
            private $template;

            /**
             * @var AbstractAlps
             */
            private $alps;

            /**
             * @var iterable
             */
            private $map;

            public function __construct(array $template, AbstractAlps $alps, iterable $map)
            {
                $this->template = $template;
                $this->alps = $alps;
                $this->map = $map;
            }

            public function render(ResourceObject $ro)
            {
                $ro->headers['content-type'] = 'text/html; charset=utf-8';
                $twig = new Environment(new ArrayLoader($this->template), ['debug' => true]);
                $twig->addExtension(new DebugExtension);
                $twig->addExtension(new RefLinkExtension);
                $twig->addExtension(new LinkifyExtension);
                $twig->addExtension(new PropTypeExtension);
                $twig->addExtension(new ConstrainExtension);
                $twig->addExtension(new TextExtension);
                $twig->addExtension(new DescExtension($this->alps));
                $twig->addExtension(new RevRouteExtension($this->map));
                $ro->view = $twig->render('index', (array) $ro->body);

                return $ro->view;
            }
        };

        return $this;
    }

    public function transfer(TransferInterface $responder, array $server)
    {
        if (! $responder instanceof FileResponder) {
            throw new LogicException(); // @codeCoverageIgnore
        }
        $uris = $this->getUri();
        $rels = $this->getRelDoc($uris);
        $responder->set($this->indexPage($uris), $this->schemaDir, $uris, $this->ext, $rels);

        return parent::transfer($responder, $server);
    }

    private function getRelDoc(array $uris) : array
    {
        $relDoc = [];
        foreach ($uris as $uri) {
            foreach ($uri->doc as $method => $docItem) {
                $links = $docItem['links'] ?? [];
                foreach ($links as $link) {
                    $relDoc[] = $link + ['link_from' => $uri->uriPath];
                }
            }
        }

        return $relDoc;
    }

    private function indexPage(array $uris) : array
    {
        $index = $this->resource->get('app://self/index')->body;
        list($curies, $links, $index) = $this->getRels($index);
        unset($index['_links']);
        $schemas = $this->getSchemas();
        $index += [
            'app_name' => $this->appName,
            'name' => $curies->name,
            'messages' => $index,
            'schemas' => $schemas,
            'uris' => $uris
        ];

        return $index;
    }

    private function getUri() : array
    {
        $uris = [];
        $meta = new Meta($this->appName, 'app');
        foreach ($meta->getGenerator('app') as $resMeta) {
            $path = $resMeta->uriPath;
            $routedUri = $this->getRoutedUri($path);
            $uri = 'app://self' . $path;
            $options = json_decode((string) $this->resource->options($uri)->view, true);
            $this->setMeta($options, $uri);
            $allow = array_keys($options);
            $uris[$routedUri] = new Uri($allow, $options, $path, $this->getUriFilePath($path));
        }

        return $uris;
    }

    private function getUriFilePath($path)
    {
        return sprintf('uri%s.%s', $path, $this->ext);
    }

    private function getRoutedUri(string $path) : string
    {
        foreach ($this->map as $route) {
            if ($route->name === $path) {
                return $route->path;
            }
        }

        return $path;
    }

    private function setMeta(array &$options, string $uri)
    {
        foreach ($options as &$option) {
            if (isset($option['schema'])) {
                $option['meta'] = new JsonSchema((string) json_encode($option['schema']), $uri);
            }
        }
    }

    private function getSchemas() : array
    {
        $schemas = [];
        foreach (glob($this->schemaDir . '/*.json') as $json) {
            $schemas[] = new JsonSchema((string) file_get_contents($json), $json);
        }

        return $schemas;
    }

    private function getRels(array $index) : array
    {
        $curieLinks = $index['_links']['curies'];
        $curies = new Curies($curieLinks);
        $links = [];
        unset($index['_links']['curies'], $index['_links']['self']);
        foreach ($index['_links'] as $nameRel => $value) {
            $rel = (string) str_replace($curies->name . ':', '', $nameRel);
            $links[$rel] = new Curie($nameRel, $value, $curies);
        }

        return [$curies, $links, $index];
    }
}
