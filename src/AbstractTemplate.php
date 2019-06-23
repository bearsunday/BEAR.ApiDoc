<?php

namespace BEAR\ApiDoc;

abstract class AbstractTemplate
{
    /**
     * Base template for all content
     */
    public $base = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>API Doc: {{ app_name}}</title>
    {% block stylesheets %}
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous">
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
     * Root Page
     */
    public $index = '{% extends \'base.html.twig\' %}
{% block title %}{{ rel }}{% endblock %}
{% block content %}
    {% if page == "index" %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">API Doc</li>
        </ol>
        {% include \'home.html.twig\' %}
    {% elseif page == "uri" %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.html">API Doc</a></li>
            <li class="breadcrumb-item">URIs</a></li>
        </ol>
        {% include \'uri.html.twig\' %}
    {% elseif page == "rel" %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.html">API Doc</a></li>
            <li class="breadcrumb-item">Rels</a></li>
        </ol>
        {% include \'rel.html.twig\' %}
    {% elseif page == "schema" %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.html">API Doc</a></li>
            <li class="breadcrumb-item">schemas</a></li>
            <li class="breadcrumb-item active">{{ schema.id }}</li>
        </ol>
        <h1>{{ schema.id }}</h1>
        {%  include \'schema.html.twig\' %}
        <p class="lead"><a href="/schemas/{{ schema.id }}">{{ schema.id }} raw file</a></p>
    {% else %}
        Unknown page runtime exception !
    {% endif %}
{% endblock %}
';

    /**
     * Home page content
     */
    public $home = '
{% for title, message in messages %}
    <p><b>{{ title|capitalize }}</b><br>{{ message|linkify|raw|nl2br }}</p>
{% endfor %}
<p><b>Link Relations</b></p>
<ul>
    {% for rel in rels %}
        <li><a href="../docs/rels/{{ rel }}.{{ ext }}">{{ rel }}</a>
    {% endfor %}
</ul>
<p><b>URIs</b></p>
<ul>
    {% for key, uri in uris %}
        <li><a href="{{ uri.filePath }}">{{ key }}</a> 
    {% endfor %}
</ul>
<p><b>Json Schemas</b></p>
<ul>
    {% for schema in schemas %}
        <li><a href="{{ schema.docHref }}">{{ schema.id }}</a> - {{ schema.title }}</li>
    {% endfor %}
</ul>
';

    /**
     * Link Relation Page
     *
     * @var string
     */
    public $rel = '
    <h1>{{ relMeta.rel }} (relation)</h1>
    <p>{{ summary }}</p>
    <div style="height: 30px"></div>
    <h2>{{ relMeta.method|upper }}</h2>
    <p><code>{{ relMeta.href }}</code></p>
    {% include \'request.html.twig\' %}';

    /**
     * URI based API page
     */
    public $uri = '
 <h1>{{ uriPath }}</h1>
 {%  include \'allow.html.twig\' %}
 {% for method_name, method in doc %}
    <hr style="width: 100%; color: grey; height: 1px; background-color:grey;" />
    <a name="{{ method_name }}"><h1>{{ method_name }}</h1></a>
    <p class="lead">{{ method.summary }}</p>
    <h4>Request</h4>
    {% set request = method.request %}
    {% include \'request.html.twig\' %}
    <h6>
        <span class="badge badge-default">Required</span>
        <span>{{ method.request.required | join(\', \')}}</span>
    </h6>

    {% if method.schema %}
        <div style="height: 30px"></div>
        <h4>Response </h4>
    {%  endif %}
    {%  set meta = method.meta%}
    {%  set schema = method.schema%}
    {%  include \'schema.html.twig\' %}
    {%  include \'link.html.twig\' %}
    {%  include \'definition.html.twig\' %}
{% endfor %}
';
    public $definition = '    {% for definition_name, definition in schema.definitions %}
        {% if loop.first %}
            <div style="height: 30px">
            </div><h5>Definitions</h5>
        {% endif %}
        {%  set schema = definition %}
        {%  include \'schema.html.twig\' %}
    {% endfor %}
';

    public $links = ' {% for link in method.links %}
        {% if loop.first %}
            <div style="height: 30px"></div>
            <h4>Link</h4>
            <table class="table table-bordered">
                <tr>
                    <th>rel</th>
                    <th>href</th>
                    <th>method</th>
                    <th>title</th>
                </tr>
        {% endif %}
                <tr>
                <td>{{ link.rel }}</td>
                <td>{{ link.href }}</td>
                <td>{{ link.method|upper }}</td>
                <td>{{ link.title }}</td>
            </tr>
        {% if loop.last %}
            </table>
        {% endif %}
    {% endfor %}';

    public $allow = '<h2>{{ href }}</h2>
        {% for method in allow %}
            {% if method == "GET" %}
                <a href="#GET" class="badge badge-success">GET</a>
            {% endif %}
            {% if method == "POST" %}
                <a href="#POST" class="badge badge-danger">POST</a>
            {% endif %}
            {% if method == "PUT" %}
                <a href="#PUT" class="badge badge-warning">PUT</a>
            {% endif %}
            {% if method == "PATCH" %}
                <a href="#PATCH" class="badge badge-warning">PATCH</a>
            {% endif %}
            {% if method == "DELETE" %}
                <a href="#DELETE" class="badge badge-warning">DELETE</a>
            {% endif %}
        {% endfor %}';

    /**
     * Request parameter table
     */
    public $request = '
    {% for param_name, parameters in request.parameters %}
        {% if loop.first %}
    <table class="table table-bordered">
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Description</th>
            <th>Default</th>
            <th>Required</th>
        </tr>
        {% endif %}
        <tr>
            <td>{{ param_name }}</td>
            <td>{{ parameters.type }}</td>
            <td>{{ parameters.description }}</td>
            <td>{{ parameters.default }}</td>
            {% if param_name in method.request.required %}
            <td>Required</td>
            {% else %}
            <td>Optional</td>            
            {% endif %}
        </tr>
    {% else %}
    <div style="height: 15px"></div>
       No parameters required.
    <div style="height: 15px"></div>
    {% endfor %}
    </table>
';

    /**
     * Schema property table
     */
    public $shcemaTable = '{% if schema.properties %}
    {%if definition_name %}
        <caption style="caption-side: top;"><a name="definitions/{{ definition_name }}">{{ definition_name }}</a></caption>
    {% endif %}
<table class="table table-bordered">
    <tr>
        <th>Property</th>
        <th>Type</th>
        <th>Description</th>
        <th>Required</th>
        <th>Constraints</th>
    </tr>
    {% for prop_name, prop in schema.properties %}
        {% set constrain_num = attribute(meta.constrainNum, prop_name) %}
        <tr>
            <td rowspan="{{ constrain_num }}">{{ prop_name }}</td>
            {% if prop.type is iterable %}
            <td rowspan="{{ constrain_num }}">{{ prop.type | join(\', \') }}</td>
            {% else %}
            <td rowspan="{{ constrain_num }}">{{ prop.type }}</td>
            {% endif %}
            <td rowspan="{{ constrain_num }}">{{ prop.description }}</td>
            {% if prop_name in schema.required %}
                <td rowspan="{{ constrain_num }}">Required</td>
                {% else %}
                <td rowspan="{{ constrain_num }}">Optional</td>            
            {% endif %}
            {% for const_name, const_val in attribute(meta.constatins, prop_name).first %}
                <td>{{ const_name }}: {{ const_val | json_encode(constant(\'JSON_PRETTY_PRINT\') b-or constant(\'JSON_UNESCAPED_SLASHES\')) | reflink | raw}}</td>
            {% else %}
                <td> </td>
            {% endfor %}
            {%if attribute(meta.constatins, prop_name).extra %}
            <tr>
            {% endif %}
                {% for const_name, const_val in attribute(meta.constatins, prop_name).extra %}
                    <td>{{ const_name }}: {{ const_val | json_encode(constant(\'JSON_PRETTY_PRINT\') b-or constant(\'JSON_UNESCAPED_SLASHES\'))}}</td>
                {% endfor %}
            {%if attribute(meta.constatins, prop_name).extra %}
            </tr>
            {% endif %}
        </tr>
    {% endfor %}
</table>
{% endif %}

{% if schema.type == \'array\' %}
    <table class="table table-bordered">
        <tr>
            <th>Type</th><th>$ref</th>
        </tr>
        {% for key, item in schema.items %}
        <tr>
            <td>Array</td>
            {% if key == \'$ref\' %}
                <td>{{ item }}<a href="../schema/{{ item  }}"><i class="fas fa-cloud-download-alt"></i></a></td>
            {% else %}
                <td>{{ item | json_encode(constant(\'JSON_PRETTY_PRINT\') b-or constant(\'JSON_UNESCAPED_SLASHES\')) }}</td>
            {% endif %}
        </tr>
        {% endfor %}
    </table>
{% endif %}
{% if schema.id is defined %}
    <div>
        <h6>
            <span class="badge badge-default">id</span>
            <span>{{ schema.id }}</span> <a href="../schema/{{ schema.id  }}"> <i class="fas fa-cloud-download-alt"></i></a>

        </h6>
    </div>
{% endif %}
{% if schema.type is defined %}
    <div>
        <h6>
            <span class="badge badge-default">type</span>
            <span>{{ schema.type }}</span>
        </h6>
    </div>
{% endif %}
{% if schema.title is defined %}
    <div>
        <h6>
            <span class="badge badge-default">title</span>
            <span>{{ schema.title }}</span>
        </h6>
    </div>
{% endif %}
{% if schema.required is defined %}
    <div>
        <h6>
            <span class="badge badge-default">required</span>
            <span>{{ schema.required | join(\', \')}}</span>
        </h6>
    </div>
{% endif %}
{% if schema.additionalProperties is defined %}
    <div>
        <h6>
            <span class="badge badge-default">additionalProperties</span>
            <span>{{ schema.additionalProperties ? \'true\' : \'false\'}}</span>
        </h6>
    </div>
{% endif %}
';
    public $embed;
    public $ext = 'html';
}
