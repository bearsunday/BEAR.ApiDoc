<?php
namespace BEAR\ApiDoc\Resource\Page\Rels {
    use Aura\Router\Router;
    use BEAR\ApiDoc\Template;
    use BEAR\Resource\Exception\HrefNotFoundException;
    use BEAR\Resource\Exception\ResourceNotFoundException;
    use BEAR\Resource\RenderInterface;
    use BEAR\Resource\ResourceInterface;
    use BEAR\Resource\ResourceObject;
    use Ray\Di\Di\Inject;
    use Ray\Di\Di\Named;

    class Doc extends ResourceObject
    {
        /**
         * @var ResourceInterface
         */
        private $resource;

        /**
         * Optional aura router
         *
         * @var Router
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
         * @Named("aura_router")
         *
         * @param null|mixed $route
         */
        public function __construct($route = null)
        {
            $this->route = $route;
        }

        /**
         * @Inject
         * @Named("schemaDir=json_schema_dir")
         */
        public function setScehmaDir(string $schemaDir = '')
        {
            $this->schemaDir = $schemaDir;
        }

        /**
         * @Inject
         */
        public function setResource(ResourceInterface $resource)
        {
            $this->resource = $resource;
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
                    $twig = new \Twig_Environment(new \Twig_Loader_Array($this->template));

                    return $twig->render('index', $ro->body);
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
            $name = $index['_links']['curies']['name'];
            $links = [];
            unset($index['_links']['curies'], $index['_links']['self']);

            foreach ($index['_links'] as $rel => $value) {
                $newRel = str_replace($name . ':', '', $rel);
                $links[$newRel] = $value;
            }
            $this->body = [
                'name' => $name,
                'message' => $index['message'],
                'links' => $links
            ];

            return $this;
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
                $optionsJson = $this->resource->options->uri($uri)()->view;
            } catch (ResourceNotFoundException $e) {
                throw new HrefNotFoundException($href, 0, $e);
            }
            $this->body = [
                'doc' => json_decode($optionsJson, true),
                'rel' => $rel,
                'href' => $href
            ];

            return $this;
        }

        private function isTemplated(array $links) : bool
        {
            return ($this->route instanceof Router && isset($links['templated']) && $links['templated'] === true) ? true : false;
        }

        private function match($tempaltedPath) : string
        {
            $routes = $this->route->getRoutes();
            foreach ($routes as $route) {
                if ($tempaltedPath == $route->path) {
                    return $route->values['path'];
                }
            }

            return $tempaltedPath;
        }
    }
}

namespace BEAR\ApiDoc {
    class Template
    {
        /**
         * Base template for all content
         */
        const BASE = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>{% block title %}Welcome!{% endblock %}</title>
    {% block stylesheets %}
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
    {% endblock %}
</head>
<body>
{% block body %}
    {% block contents %}
        <div class="container">
            {% block content %}
            {% endblock %}
        </div>
    {% endblock %}
{% endblock %}
</body>
</html>
';
        /**
         * Index page content
         */
        const INDEX = '{% extends \'base.html.twig\' %}
{% block title %}{{ rel }}{% endblock %}
{% block content %}

    {% if rel is defined %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./">API Doc</a></li>
            <li class="breadcrumb-item">rels</a></li>
            <li class="breadcrumb-item active">{{ rel }}</li>
        </ol>
        {% include \'rel.html.twig\' %}
    {% elseif schema is defined %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./">API Doc</a></li>
            <li class="breadcrumb-item">schemas</a></li>
            <li class="breadcrumb-item active">{{ schema.id }}</li>
        </ol>
        <h1>{{ schema.id }}</h1>
        {%  include \'schema.table.html.twig\' %}
        <p class="lead"><a href="/schemas/{{ schema.id }}">{{ schema.id }} raw file</a></p>
    {% else %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">API Doc</li>
        </ol>
        {% include \'home.html.twig\' %}
    {% endif %}
{% endblock %}
';

        /**
         * Home page content
         */
        const HOME = '
<p>{{ message }}</p>
<ul>
{% for rel, link in links %}
    <li><a href="?rel={{ rel }}">{{ rel }}</a></li>
{% endfor %}
</ul>
';

        /**
         * Relation page content
         */
        const REL = '<h1 class="display-4">{{ rel }}</h1>
<h2>{{ href }}</h2>
{% for method_name, method in doc %}
    <hr style="width: 100%; color: grey; height: 1px; background-color:grey;" />
    <h1>{{ method_name }}</h1>
    <p class="lead">{{ method.summary }}</p>
    <h3>Parameters</h3>
    <table class="table table-sm">
    {% for param_name, parameters in method.request.parameters %}
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Description</th>
        </tr>
        <tr>
            <td>{{ param_name }}</td>
            <td>{{ parameters.type }}</td>
            <td>{{ parameters.description }}</td>
        </tr>
    {% endfor %}
    </table>
    <h6>
        <span class="badge badge-default">Required</span>
        <span>{{ method.request.required | join(\', \')}}</span>
    </h6>

    {% if method.schema %}
        <div style="height: 30px"></div>
        <h3>Schema </h3>
        <h4><a href="?schema={{ method.schema.id }}">{{ method.schema.id }}</a></h4>
    {%  endif %}
    {%  set schema = method.schema%}
    {%  include \'schema.table.html.twig\' %}
{% endfor %}
';
        /**
         * Schema property table
         */
        const SCHEMA_TABLE = '{% if schema.properties %}
<table class="table table-sm">
    <tr>
        <th>Property</th>
        <th>Type</th>
        <th>Description</th>
        <th>Constraints</th>
    </tr>
    {% for prop_name, prop in schema.properties %}
        <tr>
            <td>{{ prop_name }}</td>
            <td>{{ prop.type }}</td>
            <td>{{ prop.description }}</td>
            <td>
                <table class="table table-condensed">
                    {% for const_name, const in prop if const_name != \'type\'%}
                        {% if not (const_name in [\'description\']) %}
                            <tr>
                                <td>{{ const_name }}</td>
                                <td>{{ const | json_encode()}}</td>
                            </tr>
                        {% endif %}
                    {% endfor %}
                </table>
            </td>
        </tr>
    {% endfor %}
</table>
{% endif %}

{% if schema.type == \'array\' %}
    <span class="label label-default">array</span>
    <table class="table">
        {% for key, item in schema.items %}
            <tr>
                <td>{{ key }}</td>
                {% if key == \'$ref\' %}
                    <td><a href="?schema={{ item }}">{{ item }}</a></td>
                {% else %}
                    <td>{{ item | json_encode()}}</td>
                {% endif %}
            </tr>
        {% endfor %}
    </table>
{% endif %}

{% if schema.required is defined %}
    <div>
        <h6>
            <span class="badge badge-default">Required</span>
            <span>{{ schema.required | join(\', \')}}</span>
        </h6>
    </div>
{% endif %}
{% if schema.additionalProperties is defined %}
    <div>
        <h6>
            <span class="badge badge-default">additionalProperties</span>
            <span>{{ schema.additionalProperties ? \'yes\' : \'no\'}}</span>
        </h6>
    </div>
{% endif %}
';
    }
}
