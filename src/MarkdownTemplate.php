<?php
namespace BEAR\ApiDoc;

final class MarkdownTemplate extends AbstractTemplate
{
    /**
     * Root Page
     */
    public $index =  /* @lang Markdown */ <<< 'EOT'
{% if page == "index" %}# API Doc
{% include 'home.html.twig' %}
{% elseif page == "uri" %}
{% include 'uri.html.twig' %}
{% elseif page == "rel" %}
{% include 'rel.html.twig' %}
{% else %}
    Unknown page runtime exception !
{% endif %}
EOT;

    /**
     * Home page content
     */
    public $home =  /* @lang Markdown */'
{% for title, message in messages %}
* **{{ title|capitalize }}** {{ message|linkify|raw|nl2br }}
{% endfor %}

## Link Relations

{% for rel in rels %}
* [{{ rel }}](rels/{{ rel }}.{{ ext }})
{% endfor %}

## URIs

{% for path, uri in uris %}
* [{{ path }}]({{ uri.filePath }})
{% endfor %}

## Schemas

{% for schema in schemas %}
* [{{  schema.id }}]({{ schema.docHref }}) - {{ schema.title }}
{% endfor %}
';

    /**
     * Link Relation Page
     */
    public $rel = /* @lang Markdown */ <<< 'EOT'
# {{ relMeta.rel }} (relation)

{{ summary }}

## {{ relMeta.method|upper }}

[{{ relMeta.href }}](../uri/{{ relMeta.href }}.{{ ext }})
{% include 'request.html.twig' %}
EOT;

    /**
     * URI based API page
     */
    public $uri = /* @lang Markdown */ <<< 'EOT'
# {{ uriPath }}
{% for method_name, method in doc %}

## {{ method_name }}

{{ method.summary }}

### Request
    {% set request = method.request %}
    {% include 'request.html.twig' %}

### Response

{%  set meta = method.meta %}
{%  set schema = method.schema %}

{%  include 'schema.html.twig' %}
{%  include 'link.html.twig' %}
{% endfor %}
EOT;

    public $definition =  /* @lang Markdown */ <<< 'EOT'
{% for definition_name, definition in schema.definitions %}
    {% if loop.first %}
EOT;

    public $links =  /* @lang Markdown */ <<< 'EOT'
{% for link in method.links %}
{% if loop.first %}

### Link
{% endif %}
 * [{{ link.rel }}](../rels/{{ link.rel }}.{{ ext }})
{% endfor %}
EOT;

    public $allow = '';

    /**
     * Request parameter table
     */
    public $request = /* @lang Markdown */ <<< 'EOT'
{% for param_name, parameters in request.parameters %}
{% if loop.first %}

| Name  | Type  | Description | Default | Required | 
|-------|-------|-------------|---------|----------|          
{% endif %}
| {{ param_name }} | {{ parameters.type }} | {{ parameters.description }} | {{ parameters.default }} | {% if param_name in request.required %} Required {% else %} Optional {% endif %}
{% else %}

(No parameters required.)
{% endfor %}
EOT;

    /**
     * Schema table
     */
    public $shcemaTable = /* @lang Markdown */ <<< 'EOT'
{% if schema.properties %}
| Name  | Type  | Description | Default | Required | 
|-------|-------|-------------|---------|----------| 
{% for prop_name, prop in schema.properties %}
| {{ prop_name }} | {{ prop.type }} | {{ prop.description }} |  {{ prop.default }} | {% if prop_name in schema.required %} Required {% else %} Optional {% endif %} | 
{% endfor %}
{% endif %}

{% if schema.type == 'array' %}
    {% for key, item in schema.items %}
* ** Array ** 
            {% if key == '$ref' %}
                <td>{{ item }}<a href="../schema/{{ item  }}"><i class="fas fa-cloud-download-alt"></i></a></td>
            {% else %}
                <td>{{ item | json_encode(constant('JSON_PRETTY_PRINT') b-or constant('JSON_UNESCAPED_SLASHES')) }}</td>
            {% endif %}
    {% endfor %}
{% endif %}

{% if schema.type is defined %}
* **type** {{ schema.type }}</span>
{% if meta.id is defined %}
* **schema** [{{ meta.id }}](../{{ meta.docHref }})</span>
{% endif %}
{% endif %}

EOT;

    public $ext = 'md';
}
