<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

use function count;
use function file_get_contents;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_object;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strtoupper;
use function substr;
use function ucfirst;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PATHINFO_FILENAME;

/**
 * Generates single-page HTML API documentation
 *
 * @psalm-import-type HtmlParamArray from Types
 * @psalm-import-type HtmlMethodArray from Types
 * @psalm-import-type HtmlPropertyArray from Types
 * @psalm-import-type HtmlObjectArray from Types
 * @psalm-import-type HtmlRelationArray from Types
 */
final class HtmlGenerator
{
    /** @var array<string, array<string, HtmlMethodArray>> */
    private array $endpoints = [];

    /** @var array<string, HtmlObjectArray> */
    private array $objects = [];

    /** @var array<string, array{embeds: array<HtmlRelationArray>, links: array<HtmlRelationArray>}> */
    private array $objectRelations = [];

    public function __construct(
        private readonly Config $config,
        private readonly string $requestSchemaDir,
        private readonly string $responseSchemaDir,
    ) {
    }

    public function generate(): string
    {
        foreach ($this->config->resourceFiles as $meta) {
            $path = $this->config->routes[$meta->uriPath] ?? $meta->uriPath;
            $this->processResource($path, new ReflectionClass($meta->class));
        }

        return $this->render();
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function processResource(string $path, ReflectionClass $class): void
    {
        $methods = $class->getMethods();

        foreach ($methods as $method) {
            $name = $method->getName();
            if (! in_array($name, ['onGet', 'onPut', 'onPost', 'onPatch', 'onDelete'])) {
                continue;
            }

            $httpMethod = strtoupper(substr($name, 2));
            $this->processMethod($path, $httpMethod, $method);
        }
    }

    private function processMethod(string $path, string $httpMethod, ReflectionMethod $method): void
    {
        // Get PHPDoc
        $summary = '';
        $paramDescriptions = [];
        $docComment = $method->getDocComment();
        if (is_string($docComment)) {
            $factory = DocBlockFactory::createInstance();
            $docBlock = $factory->create($docComment);
            $summary = $docBlock->getSummary();
            foreach ($docBlock->getTagsByName('param') as $param) {
                /** @var \phpDocumentor\Reflection\DocBlock\Tags\Param $param */
                $paramDescriptions[(string) $param->getVariableName()] = (string) $param->getDescription();
            }
        }

        // Get JsonSchema attribute for request/response
        $requestSchema = null;
        $responseSchemaName = null;
        $attributes = $method->getAttributes(JsonSchema::class);
        if ($attributes !== []) {
            $schemaAttr = $attributes[0]->newInstance();
            if ($schemaAttr->params !== '') {
                $requestSchema = $this->loadSchema($this->requestSchemaDir, $schemaAttr->params);
            }

            if ($schemaAttr->schema !== '') {
                $responseSchema = $this->loadSchema($this->responseSchemaDir, $schemaAttr->schema);
                if ($responseSchema !== null) {
                    $responseSchemaName = $responseSchema->title ?: ucfirst(pathinfo($schemaAttr->schema, PATHINFO_FILENAME));
                    $this->addObject($responseSchemaName, $responseSchema);
                }
            }
        }

        // Get embeds and links
        $embeds = [];
        $links = [];
        foreach ($method->getAttributes(Embed::class) as $attr) {
            $embed = $attr->newInstance();
            $embeds[] = ['rel' => $embed->rel, 'src' => $embed->src];
        }

        foreach ($method->getAttributes(Link::class) as $attr) {
            $link = $attr->newInstance();
            $links[] = ['rel' => $link->rel, 'href' => $link->href];
        }

        // Store relations for the response object
        if ($responseSchemaName !== null && ($embeds !== [] || $links !== [])) {
            $this->addObjectRelations($responseSchemaName, $embeds, $links);
        }

        // Get parameters
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();
            $typeName = $paramType instanceof ReflectionNamedType ? $paramType->getName() : 'string';

            // Get description from PHPDoc or request schema
            $description = $paramDescriptions[$paramName] ?? '';
            $example = '';
            $constraints = [];

            if ($requestSchema !== null && isset($requestSchema->props[$paramName])) {
                $prop = $requestSchema->props[$paramName];
                if ($description === '') {
                    $description = $prop->description;
                }

                $example = $prop->example;
                $constraints = $prop->constraints->constrains;
            }

            $params[] = [
                'name' => $paramName,
                'type' => $this->normalizeType($typeName),
                'description' => $description,
                'required' => ! $param->isOptional(),
                'example' => $example,
                'constraints' => $constraints,
            ];
        }

        if (! isset($this->endpoints[$path])) {
            $this->endpoints[$path] = [];
        }

        $this->endpoints[$path][$httpMethod] = [
            'summary' => $summary,
            'params' => $params,
            'response' => $responseSchemaName,
            'embeds' => $embeds,
            'links' => $links,
        ];
    }

    private function loadSchema(string $dir, string $file): ?Schema
    {
        $schemaFile = sprintf('%s/%s', $dir, $file);
        if (! is_file($schemaFile)) {
            return null;
        }

        $schemaJson = json_decode((string) file_get_contents($schemaFile));
        if (! is_object($schemaJson)) {
            return null;
        }

        $fileInfo = new SplFileInfo($schemaFile);
        /** @var ArrayObject<string, string> $emptyDictionary */
        $emptyDictionary = new ArrayObject();

        return new Schema($fileInfo, $schemaJson, $emptyDictionary);
    }

    private function addObject(string $name, Schema $schema): void
    {
        if (isset($this->objects[$name])) {
            return;
        }

        $properties = [];
        foreach ($schema->props as $propName => $prop) {
            if ($propName === '_links' || $propName === '_embedded') {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $formatValue = $prop->constraints->constrains['format'] ?? null;
            $format = is_string($formatValue) ? $formatValue : null;
            $constraints = $prop->constraints->constrains;
            unset($constraints['format']);

            $properties[] = [
                'name' => $propName,
                'type' => $prop->type,
                'description' => $prop->description,
                'example' => $prop->example,
                'format' => $format,
                'constraints' => $constraints,
            ];
        }

        $this->objects[$name] = [
            'name' => $name,
            'properties' => $properties,
        ];
    }

    /**
     * @param array<array{rel: string, src: string}>  $embeds
     * @param array<array{rel: string, href: string}> $links
     */
    private function addObjectRelations(string $objectName, array $embeds, array $links): void
    {
        if (! isset($this->objectRelations[$objectName])) {
            $this->objectRelations[$objectName] = ['embeds' => [], 'links' => []];
        }

        foreach ($embeds as $embed) {
            $target = $this->extractTargetFromUri($embed['src']);
            $this->objectRelations[$objectName]['embeds'][] = [
                'rel' => $embed['rel'],
                'target' => $target,
            ];
        }

        foreach ($links as $link) {
            $target = $this->extractTargetFromUri($link['href']);
            $this->objectRelations[$objectName]['links'][] = [
                'rel' => $link['rel'],
                'target' => $target,
            ];
        }
    }

    private function extractTargetFromUri(string $uri): string
    {
        // Extract resource name from URI like "/ticket{?id}" -> "Ticket"
        preg_match('/^\/([a-z_-]+)/i', $uri, $matches);

        return isset($matches[1]) ? ucfirst($matches[1]) : ucfirst($uri);
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'boolean' => 'bool',
            default => $type,
        };
    }

    private function render(): string
    {
        $title = htmlspecialchars($this->config->title ?: 'API Documentation');

        $description = htmlspecialchars($this->config->description ?: '');
        $css = $this->getCss();
        $endpointsHtml = $this->renderEndpoints();
        $objectsHtml = $this->renderObjects();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{$title}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/3.0.1/github-markdown.min.css">
<style>
{$css}
</style>
</head>
<body>
<div class="markdown-body">
<h1>{$title}</h1>
<p>{$description}</p>

<h2>Endpoints</h2>
{$endpointsHtml}

<h2>Objects</h2>
{$objectsHtml}

</div>
</body>
</html>
HTML;
    }

    private function renderEndpoints(): string
    {
        $rows = '';

        foreach ($this->endpoints as $path => $methods) {
            $rows .= $this->renderEndpointRows($path, $methods);
        }

        return <<<HTML
<table>
<thead>
<tr>
  <th>Path</th>
  <th>Method</th>
  <th>Params</th>
  <th>Description</th>
  <th>Meta</th>
  <th>Example</th>
  <th>Response</th>
</tr>
</thead>
<tbody>
{$rows}
</tbody>
</table>
HTML;
    }

    /**
     * @param array<string, HtmlMethodArray> $methods
     */
    private function renderEndpointRows(string $path, array $methods): string
    {
        $rows = '';
        $totalRows = $this->countTotalRows($methods);
        $isFirstPath = true;

        foreach ($methods as $httpMethod => $data) {
            $params = $data['params'];
            $paramCount = count($params);
            $methodRowspan = $paramCount > 0 ? $paramCount : 1;

            if ($paramCount === 0) {
                $pathCell = $isFirstPath ? sprintf('<td rowspan="%d" class="path-cell">%s</td>', $totalRows, htmlspecialchars($path)) : '';
                $methodHtml = $this->renderMethodBadge($httpMethod);
                $responseHtml = $this->renderResponseLink($data['response']);

                $rows .= <<<HTML
<tr>
  {$pathCell}
  <td>{$methodHtml}</td>
  <td></td>
  <td></td>
  <td></td>
  <td></td>
  <td>{$responseHtml}</td>
</tr>

HTML;
                $isFirstPath = false;
                continue;
            }

            $isFirstParam = true;
            foreach ($params as $param) {
                $pathCell = '';
                $methodCell = '';
                $responseCell = '';

                if ($isFirstPath && $isFirstParam) {
                    $pathCell = sprintf('<td rowspan="%d" class="path-cell">%s</td>', $totalRows, htmlspecialchars($path));
                }

                if ($isFirstParam) {
                    $methodHtml = $this->renderMethodBadge($httpMethod);
                    $methodCell = sprintf('<td rowspan="%d">%s</td>', $methodRowspan, $methodHtml);
                    $responseCell = sprintf('<td rowspan="%d">%s</td>', $methodRowspan, $this->renderResponseLink($data['response']));
                }

                $rows .= $this->renderParamRow($pathCell, $methodCell, $param, $responseCell);
                $isFirstParam = false;
                $isFirstPath = false;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, HtmlMethodArray> $methods
     */
    private function countTotalRows(array $methods): int
    {
        $total = 0;
        foreach ($methods as $data) {
            $paramCount = count($data['params']);
            $total += $paramCount > 0 ? $paramCount : 1;
        }

        return $total;
    }

    private function renderMethodBadge(string $method): string
    {
        $typeClass = match ($method) {
            'GET' => 'safe',
            'PUT' => 'idempotent',
            default => 'unsafe',
        };

        return sprintf('<span class="ti %s"></span>%s', $typeClass, $method);
    }

    private function renderResponseLink(?string $schemaName): string
    {
        if ($schemaName === null) {
            return '';
        }

        return sprintf('<a href="#%s" class="schema-link">%s</a>', htmlspecialchars($schemaName), htmlspecialchars($schemaName));
    }

    /**
     * @param HtmlParamArray $param
     */
    private function renderParamRow(string $pathCell, string $methodCell, array $param, string $responseCell): string
    {
        $nameHtml = sprintf('<span class="param">%s</span>', htmlspecialchars($param['name']));
        if ($param['required']) {
            $nameHtml .= '<span class="req">*</span>';
        }

        $metaHtml = $this->renderMetaBadges($param['type'], $param['constraints']);
        $exampleHtml = $param['example'] !== '' ? htmlspecialchars($param['example']) : '';
        $descriptionHtml = htmlspecialchars($param['description']);

        return <<<HTML
<tr>
  {$pathCell}
  {$methodCell}
  <td>{$nameHtml}</td>
  <td>{$descriptionHtml}</td>
  <td>{$metaHtml}</td>
  <td class="example">{$exampleHtml}</td>
  {$responseCell}
</tr>

HTML;
    }

    /**
     * @param array<string, mixed> $constraints
     */
    private function renderMetaBadges(string $type, array $constraints): string
    {
        $badges = [];

        // Type badge
        $typeClass = 'type-' . $type;
        $badges[] = sprintf('<span class="badge %s">%s</span>', $typeClass, htmlspecialchars($type));

        // Format badge (special case)
        if (isset($constraints['format'])) {
            $formatStr = is_scalar($constraints['format']) ? (string) $constraints['format'] : '';
            $badges[] = sprintf('<span class="badge format">format: %s</span>', htmlspecialchars($formatStr));
            unset($constraints['format']);
        }

        // Keys to skip in constraint badges (complex types not suitable for display)
        $skipKeys = ['items', 'properties', '$ref', 'definitions', 'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else'];

        // Other constraint badges
        /** @psalm-suppress MixedAssignment */
        foreach ($constraints as $key => $value) {
            // Skip complex structural constraints
            if (in_array($key, $skipKeys, true)) {
                continue;
            }

            if (is_object($value) || is_array($value)) {
                $value = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $value = is_scalar($value) ? (string) $value : '';
            }

            $badges[] = sprintf('<span class="badge constraint">%s: %s</span>', htmlspecialchars($key), htmlspecialchars($value));
        }

        $badgesHtml = implode("\n      ", $badges);

        return <<<HTML
<div class="extra-info">
      {$badgesHtml}
    </div>
HTML;
    }

    private function renderObjects(): string
    {
        $html = '';

        foreach ($this->objects as $name => $object) {
            $html .= $this->renderObject($name, $object);
        }

        return $html;
    }

    /**
     * @param HtmlObjectArray $object
     */
    private function renderObject(string $name, array $object): string
    {
        $rows = '';

        // Render properties
        foreach ($object['properties'] as $prop) {
            $rows .= $this->renderPropertyRow($prop);
        }

        // Render embeds
        if (isset($this->objectRelations[$name]['embeds'])) {
            foreach ($this->objectRelations[$name]['embeds'] as $embed) {
                $rows .= $this->renderRelationRow($embed, 'embed');
            }
        }

        // Render links
        if (isset($this->objectRelations[$name]['links'])) {
            foreach ($this->objectRelations[$name]['links'] as $link) {
                $rows .= $this->renderRelationRow($link, 'link');
            }
        }

        $escapedName = htmlspecialchars($name);

        return <<<HTML
<div class="object-section" id="{$escapedName}">
<h3 class="object-name">{$escapedName}</h3>
<table>
<thead>
<tr><th>Name</th><th>Description</th><th>Meta</th><th>Example</th></tr>
</thead>
<tbody>
{$rows}
</tbody>
</table>
</div>

HTML;
    }

    /**
     * @param HtmlPropertyArray $prop
     */
    private function renderPropertyRow(array $prop): string
    {
        $nameHtml = sprintf('<span class="prop-name">%s</span>', htmlspecialchars($prop['name']));
        $metaHtml = $this->renderPropertyMeta($prop);
        $exampleHtml = $prop['example'] !== '' && $prop['example'] !== null ? htmlspecialchars($prop['example']) : '';
        $descriptionHtml = htmlspecialchars($prop['description']);

        return <<<HTML
<tr>
  <td>{$nameHtml}</td>
  <td>{$descriptionHtml}</td>
  <td>{$metaHtml}</td>
  <td class="example">{$exampleHtml}</td>
</tr>

HTML;
    }

    /**
     * @param HtmlPropertyArray $prop
     */
    private function renderPropertyMeta(array $prop): string
    {
        $badges = [];

        // Type badge
        $type = $this->normalizeType($prop['type']);
        // Sanitize class name (remove special chars like |)
        $typeClass = 'type-' . (preg_replace('/[^a-zA-Z0-9-]/', '', $type) ?? $type);
        $badges[] = sprintf('<span class="badge %s">%s</span>', $typeClass, htmlspecialchars($type));

        // Format badge
        if ($prop['format'] !== null) {
            $badges[] = sprintf('<span class="badge format">format: %s</span>', htmlspecialchars($prop['format']));
        }

        // Keys to skip in constraint badges (complex types not suitable for display)
        $skipKeys = ['items', 'properties', '$ref', 'definitions', 'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else'];

        // Constraint badges
        /** @psalm-suppress MixedAssignment */
        foreach ($prop['constraints'] as $key => $value) {
            // Skip complex structural constraints
            if (in_array($key, $skipKeys, true)) {
                continue;
            }

            if (is_object($value) || is_array($value)) {
                $value = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $value = is_scalar($value) ? (string) $value : '';
            }

            $badges[] = sprintf('<span class="badge constraint">%s: %s</span>', htmlspecialchars($key), htmlspecialchars($value));
        }

        $badgesHtml = implode("\n      ", $badges);

        return <<<HTML
<div class="extra-info">
      {$badgesHtml}
    </div>
HTML;
    }

    /**
     * @param HtmlRelationArray $relation
     */
    private function renderRelationRow(array $relation, string $type): string
    {
        $rowClass = $type === 'embed' ? 'embed-row' : 'link-row';
        $rel = htmlspecialchars($relation['rel']);
        $target = htmlspecialchars($relation['target']);
        $description = ucfirst($rel);

        return <<<HTML
<tr class="{$rowClass}">
  <td class="prop-name"><a href="#{$target}">{$rel}</a></td>
  <td>{$description}</td>
  <td>
    <div class="extra-info">
      <span class="badge {$type}">{$type}</span>
    </div>
  </td>
  <td class="example"></td>
</tr>

HTML;
    }

    private function getCss(): string
    {
        return <<<'CSS'
html{scroll-behavior:smooth;}
body{margin:0;padding:0;background:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
.markdown-body{background:#fff;padding:45px;max-width:none;margin:0 auto;overflow:visible;}
a{cursor:pointer;color:#0366d6;text-decoration:none;}
a:hover{text-decoration:underline;}
h1,h2,h3{margin-top:0;}
/* Type indicator */
.ti{display:inline-block;width:10px;height:10px;margin-right:4px;border:1px solid #000;vertical-align:middle;}
.ti.safe{
    background-color:#00A86B;
    background-image:linear-gradient(45deg,#008000 25%,transparent 25%,transparent 75%,#008000 75%,#008000),linear-gradient(45deg,#008000 25%,transparent 25%,transparent 75%,#008000 75%,#008000);
    background-size:6px 6px;
    background-position:0 0,3px 3px;
}
.ti.unsafe{
    background-color:#FF4136;
    background-image:repeating-linear-gradient(45deg,#FF4136,#FF4136 3px,#FF725C 3px,#FF725C 6px);
}
.ti.idempotent{
    background-color:#D4A000;
    background-image:radial-gradient(#FFB700 20%,transparent 20%),radial-gradient(#FFB700 20%,transparent 20%);
    background-size:6px 6px;
    background-position:0 0,3px 3px;
}
/* Table */
table{width:100%;border-collapse:collapse;margin:20px 0;}
th,td{padding:6px 10px;border:1px solid #ddd;text-align:left;vertical-align:top;}
th{background:#f6f8fa;font-weight:600;}
tr:hover{background-color:#f5f5f5;}
/* Path cell */
.path-cell{font-family:'SFMono-Regular',Consolas,monospace;font-weight:600;}
/* Param */
.param{font-family:'SFMono-Regular',Consolas,monospace;}
.req{color:#cf222e;}
/* Extra Info */
.extra-info{display:flex;flex-direction:column;gap:2px;align-items:flex-start;}
.extra-item{display:flex;align-items:center;line-height:normal;}
/* Badge style - outline */
.badge{display:inline-block;padding:1px 6px;border-radius:4px;font-size:0.75em;margin-right:4px;vertical-align:middle;width:fit-content;border:1px solid;}
.badge.type-string{background:#EAF5FF;border-color:#5C9EE8;color:#0550ae;}
.badge.type-int{background:#F5F0FF;border-color:#D8B9FF;color:#8957e5;}
.badge.type-integer{background:#F5F0FF;border-color:#D8B9FF;color:#8957e5;}
.badge.type-bool{background:#DAFBE1;border-color:#A7F3D0;color:#116329;}
.badge.type-boolean{background:#DAFBE1;border-color:#A7F3D0;color:#116329;}
.badge.type-array{background:#FFF5E6;border-color:#FFD9B3;color:#D97506;}
.badge.type-object{background:#FFF0F5;border-color:#FFB6C1;color:#CF222E;}
.badge.type-mixed{background:#f6f8fa;border-color:#d0d7de;color:#57606a;}
.badge.constraint{background:#f6f8fa;border-color:#d0d7de;color:#57606a;}
.badge.format{background:#DAFBE1;border-color:#A7F3D0;color:#116329;}
.badge.embed{background:#F5F0FF;border-color:#D8B9FF;color:#8957e5;}
.badge.link{background:#EAF5FF;border-color:#B8DFFF;color:#0366d6;}
/* Sticky rows */
.embed-row{background:linear-gradient(135deg,#FFFBEB 0%,#FEF3C7 100%);box-shadow:3px 3px 6px rgba(0,0,0,0.15);border-left:3px solid #F59E0B;}
.link-row{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 100%);box-shadow:3px 3px 6px rgba(0,0,0,0.15);border-left:3px solid #3B82F6;}
/* Example */
.example{font-family:'SFMono-Regular',Consolas,monospace;font-size:0.85em;color:#57606a;}
/* Response */
.schema-link{font-family:'SFMono-Regular',Consolas,monospace;font-weight:500;}
/* Object table */
.object-section{margin:30px 0;}
.object-name{font-family:'SFMono-Regular',Consolas,monospace;font-weight:600;font-size:1.1em;}
.prop-name{font-family:'SFMono-Regular',Consolas,monospace;color:#24292f;}
.prop-name a{color:#0366d6;text-decoration:underline;}
CSS;
    }
}
