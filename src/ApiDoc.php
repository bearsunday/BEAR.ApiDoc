<?php
namespace BEAR\ApiDoc;

use Aura\Router\Router;
use Aura\Router\RouterContainer;
use BEAR\Resource\Exception\HrefNotFoundException;
use BEAR\Resource\Exception\ResourceNotFoundException;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use Ray\Di\Di\Inject;
use Ray\Di\Di\Named;
use Twig_Extension_Debug;
use function file_get_contents;
use function json_decode;
use function json_encode;
use function str_replace;

final class ApiDoc extends ResourceObject
{
    /**
     * @var ResourceInterface
     */
    private $resource;

    /**
     * Optional aura router
     *
     * @var RouterContainer
     */
    private $route;

    /**
     * @var string
     */
    private $schemaDir;

    private $template = [
        'index' => Template::INDEX,
        'base.html.twig' => Template::BASE,
        'home.html.twig' => Template::HOME,
        'rel.html.twig' => Template::REL,
        'schema.table.html.twig' => Template::SCHEMA_TABLE
    ];

    /**
     * @Named("schemaDir=json_schema_dir")
     */
    public function __construct(ResourceInterface $resource, RouterContainer $routerContainer = null, string $schemaDir = '')
    {
        $this->resource = $resource;
        $this->route = $routerContainer;
        $this->schemaDir = $schemaDir;
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
                $ro->view = $twig->render('index', $ro->body);

                return $ro->view;
            }
        };
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

    public function write(string $docDir)
    {
        $this->writeIndex($docDir);
        $this->indexPage();
        $this->writeRel($this->body['links'], $docDir);
    }

    private function writeIndex(string $docDir)
    {
        if (! is_dir($docDir) && ! mkdir($docDir, 0777, true) && ! is_dir($docDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $docDir));
        }
        file_put_contents($docDir . '/index.html', (string) $this->indexPage());
    }

    private function writeRel(array $links, string $docDir)
    {
        foreach ($links as $rel => $relMeta) {
            $this->view = null;
            $ro = $this->onGet($rel);
            $view = (string) $ro;
            $relsDir = $docDir . '/rels';
            $schemaDir = $docDir . '/schema';
            if (! is_dir($relsDir) && ! mkdir($relsDir, 0777, true) && ! is_dir($relsDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $relsDir));
            }
            if (! is_dir($schemaDir) && ! mkdir($schemaDir, 0777, true) && ! is_dir($schemaDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $schemaDir));
            }
            file_put_contents("{$relsDir}/{$rel}.html", $view);
        }
        $this->copyJson($docDir, $ro);
    }

    private function schemaPage(string $id) : ResourceObject
    {
        $path = realpath($this->schemaDir . '/' . $id);
        $isInvalidFilePath = (strncmp($path, $this->schemaDir, strlen($this->schemaDir)) !== 0);
        if ($isInvalidFilePath) {
            throw new \DomainException($id);
        }
        $schema = (array) json_decode(file_get_contents($path), true);
        $this->body['schema'] = $schema;

        return $this;
    }

    private function indexPage() : ResourceObject
    {
        $index = $this->resource->uri('app://self/index')()->body;
        $curies = new Curies($index['_links']['curies']);
        $links = [];
        unset($index['_links']['curies'], $index['_links']['self']);
        foreach ($index['_links'] as $nameRel => $value) {
            $rel = str_replace($curies->name . ':', '', $nameRel);
            $links[$rel] = new Curie($nameRel, $value, $curies);
        }
        $schemas = $this->getSchemas();
        $this->body = [
            'name' => $curies->name,
            'message' => $index['message'],
            'links' => $links,
            'schemas' => $schemas
        ];

        return $this;
    }

    private function getSchemas() : array
    {
        $schemas = [];
        foreach (glob($this->schemaDir . '/*.json') as $json) {
            $schemas[] = new JsonSchema(file_get_contents($json));
        }

        return $schemas;
    }

    private function relPage(string $rel) : ResourceObject
    {
        $index = $this->resource->options->uri('app://self/')()->body;
        $namedRel = sprintf('%s:%s', $index['_links']['curies']['name'], $rel);
        $links = $index['_links'];
        if (! isset($links[$namedRel]['href'])) {
            throw new ResourceNotFoundException($rel);
        }
        $href = $links[$namedRel]['href'];
        $path = $this->isTemplated($links[$namedRel]) ? $this->match($href) : $href;
        $uri = 'app://self' . $path;
        try {
            $optionsJson = $this->resource->options($uri)->view;
        } catch (ResourceNotFoundException $e) {
            throw new HrefNotFoundException($href, 0, $e);
        }
        $options = json_decode($optionsJson, true);
        foreach ($options as &$option) {
            if (isset($option['schema'])) {
                $option['meta'] = new JsonSchema(json_encode($option['schema']));
            }
        }
        $this->body = [
            'doc' => $options,
            'rel' => $rel,
            'href' => $href
        ];

        return $this;
    }

    private function isTemplated(array $links) : bool
    {
        $isTemplated = $this->route instanceof RouterContainer && isset($links['templated']) && $links['templated'] === true;

        return $isTemplated;
    }

    private function match($tempaltedPath) : string
    {
        $routes = $this->route->getMap()->getRoutes();
        foreach ($routes as $route) {
            if ($tempaltedPath === $route->path) {
                return $route->name;
            }
        }

        return $tempaltedPath;
    }

    private function copyJson(string $docDir, self $ro)
    {
        foreach (glob($ro->schemaDir . '/*.json') as $json) {
            $dest = str_replace($this->schemaDir, "{$docDir}/schema", $json);
            copy($json, $dest);
        }
    }
}
