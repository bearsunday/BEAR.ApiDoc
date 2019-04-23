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
* [{{ rel }}](rels/{{ rel }}.html)
{% endfor %}

## URIs

{% for path, uri in uris %}
* [{{ path }}]({{ uri.filePath }})
{% endfor %}

## Json Schemas

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

[{{ relMeta.href }}](uri{{ relMeta.href }}.html)
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

{% if method.schema %}
### Response
    {%  endif %}

    {%  set meta = method.meta%}
    {%  set schema = method.schema%}
    {%  include 'schema.html.twig' %}
    {%  include 'link.html.twig' %}
    {%  include 'definition.html.twig' %}
{% endfor %}
EOT;

    public $definition =  /* @lang Markdown */ <<< 'EOT'
{% for definition_name, definition in schema.definitions %}
    {% if loop.first %}
#### Definitions
    {% endif %}
    {%  set schema = definition %}
    {%  include \'schema.html.twig\' %}
{% endfor %}
EOT;

    public $links =  /* @lang Markdown */ <<< 'EOT'
{% for link in method.links %}
    {% if loop.first %}
#### Link
| Rel  | Href  | Method | Title | 
    {% endif %}
| {{ link.rel }} | {{ link.href }} | {{ link.method|upper }} | {{ link.title }} |
{% endfor %}';
EOT;

    public $allow = '';

    /**
     * Request parameter table
     */
    public $request = /* @lang Markdown */
        <<< 'EOT'
{% for param_name, parameters in request.parameters %}
{% if loop.first %}

| Name  | Type  | Description | Default | Required | 
|-------|-------|-------------|---------|----------|          
{% endif %}
| {{ param_name }} | {{ parameters.type }} | {{ parameters.description }} | {{ parameters.default }} | {% if param_name in method.request.required %}Required{% else %}Optional{% endif %}
{% else %}
   No parameters required.
{% endfor %}
EOT;
    public $shcemaTable = '';
    public $ext = 'md';
}
