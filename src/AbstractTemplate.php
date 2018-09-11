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
    <title>{% block title %}Welcome!{% endblock %}</title>
    {% block stylesheets %}
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous">
        <css 
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
    public $index = '{% extends \'base.html.twig\' %}
{% block title %}{{ rel }}{% endblock %}
{% block content %}

    {% if rel is defined %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.html">API Doc</a></li>
            <li class="breadcrumb-item">rels</a></li>
            <li class="breadcrumb-item active">{{ rel }}</li>
        </ol>
        {% include \'rel.html.twig\' %}
    {% elseif schema is defined %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.html">API Doc</a></li>
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
    public $home = '
{% for title, message in messages %}
    <p><b>{{ title|capitalize }}</b><br>{{ message|linkify|raw|nl2br }}</p>
{% endfor %}
<p><b>Link Relations</b></p>
<ul>
    {% for link in links %}
        <li><a href="{{ link.docUri }}">{{ link.rel }}</a> - {{ link.title}} - <span style="color: gray"><conde>{{ link.href}}</conde></span>
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
     * Relation page content
     */
    public $rel = '<h2>{{ href }}</h2>
{% for method_name, method in doc %}
    <hr style="width: 100%; color: grey; height: 1px; background-color:grey;" />
    <h1>{{ method_name }}</h1>
    <p class="lead">{{ method.summary }}</p>
    <h4>Request</h4>
    {% for param_name, parameters in method.request.parameters %}
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
    {%  include \'schema.table.html.twig\' %}
    {% for definition_name, definition in schema.definitions %}
        {% if loop.first %}
            <div style="height: 30px">
            </div><h5>Definitions</h5>
        {% endif %}
        {%  set schema = definition %}
        {%  include \'schema.table.html.twig\' %}
    {% endfor %}
{% endfor %}
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
                    <td>{{ const_name }}: {{ const_val }}</td>
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
}
