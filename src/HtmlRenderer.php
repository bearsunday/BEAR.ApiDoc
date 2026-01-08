<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function count;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function is_scalar;
use function json_encode;
use function preg_replace;
use function sprintf;
use function ucfirst;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders HTML for API documentation
 *
 * @psalm-import-type HtmlParamArray from Types
 * @psalm-import-type HtmlMethodArray from Types
 * @psalm-import-type HtmlPropertyArray from Types
 * @psalm-import-type HtmlObjectArray from Types
 * @psalm-import-type HtmlRelationArray from Types
 */
final class HtmlRenderer
{
    /**
     * @param array<string, array<string, HtmlMethodArray>>                                                                             $endpoints
     * @param array<string, HtmlObjectArray>                                                                                            $objects
     * @param array<string, array{embeds: array<array{rel: string, target: string}>, links: array<array{rel: string, target: string}>}> $objectRelations
     * @param array<array{rel: string, href: string}>                                                                                   $links
     */
    public function render(
        string $title,
        string $description,
        array $endpoints,
        array $objects,
        array $objectRelations,
        array $links = [],
    ): string {
        $escapedTitle = htmlspecialchars($title ?: 'API Documentation');
        $escapedDescription = $this->convertMarkdownLinks($description ?: '');
        $css = $this->getCss();
        $endpointsHtml = $this->renderEndpoints($endpoints);
        [$objectsHtml, $arraysHtml] = $this->renderObjectsAndArrays($objects, $objectRelations);
        $linksHtml = $this->renderLinks($links);

        $objectsSection = $objectsHtml !== '' ? "<h2>Objects</h2>\n{$objectsHtml}" : '';
        $arraysSection = $arraysHtml !== '' ? "<h2>Arrays</h2>\n{$arraysHtml}" : '';
        $linksSection = $linksHtml !== '' ? "<h2>Links</h2>\n{$linksHtml}" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{$escapedTitle}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/3.0.1/github-markdown.min.css">
<style>
{$css}
</style>
</head>
<body>
<div class="markdown-body">
<h1>{$escapedTitle}</h1>
<p>{$escapedDescription}</p>

<h2>Endpoints</h2>
{$endpointsHtml}

{$objectsSection}

{$arraysSection}

{$linksSection}

</div>
</body>
</html>
HTML;
    }

    /**
     * @param array<string, array<string, HtmlMethodArray>> $endpoints
     */
    private function renderEndpoints(array $endpoints): string
    {
        $rows = '';

        foreach ($endpoints as $path => $methods) {
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
                $methodHtml = $this->renderMethodBadge($httpMethod, $data['summary'], $data['description'], $data['alps']);
                $responseHtml = $this->renderResponseLink($data['response']);

                $rows .= <<<HTML
<tr>
  {$pathCell}
  <td class="method-cell">{$methodHtml}</td>
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
                    $methodHtml = $this->renderMethodBadge($httpMethod, $data['summary'], $data['description'], $data['alps']);
                    $methodCell = sprintf('<td rowspan="%d" class="method-cell">%s</td>', $methodRowspan, $methodHtml);
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

    /**
     * @param array<string> $alps
     */
    private function renderMethodBadge(string $method, string $title = '', string $description = '', array $alps = []): string
    {
        $typeClass = match ($method) {
            'GET' => 'safe',
            'PUT' => 'idempotent',
            default => 'unsafe',
        };

        $html = sprintf('<span class="ti %s"></span>%s', $typeClass, $method);
        if ($title !== '') {
            $html .= sprintf('<div class="method-title">%s</div>', htmlspecialchars($title));
        }

        if ($description !== '') {
            $html .= sprintf('<div class="method-desc">%s</div>', htmlspecialchars($description));
        }

        if ($alps !== []) {
            $alpsLinks = [];
            foreach ($alps as $alpsId) {
                $alpsLinks[] = sprintf('<a href="alps.html#%s" class="alps-link">%s</a>', htmlspecialchars($alpsId), htmlspecialchars($alpsId));
            }

            $html .= sprintf('<div class="method-alps">%s</div>', implode(', ', $alpsLinks));
        }

        return $html;
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
        $nameHtml = $this->renderParamName($param['name'], $param['alps']);
        if ($param['required']) {
            $nameHtml .= '<span class="req">*</span>';
        }

        $metaHtml = $this->renderMetaBadges($param['type'], $param['constraints'], $param['example']);
        $descriptionHtml = htmlspecialchars($param['description']);

        return <<<HTML
<tr>
  {$pathCell}
  {$methodCell}
  <td>{$nameHtml}</td>
  <td class="param-desc">{$descriptionHtml}</td>
  <td>{$metaHtml}</td>
  {$responseCell}
</tr>

HTML;
    }

    private function renderParamName(string $name, ?string $alpsTitle): string
    {
        $escapedName = htmlspecialchars($name);

        if ($alpsTitle === null) {
            return sprintf('<span class="param">%s</span>', $escapedName);
        }

        return sprintf(
            '<a href="alps.html#%s" class="param alps-param" title="%s">%s</a>',
            $escapedName,
            htmlspecialchars($alpsTitle),
            $escapedName,
        );
    }

    /**
     * @param array<string, mixed> $constraints
     */
    private function renderMetaBadges(string $type, array $constraints, string $example = ''): string
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

        // Keys to skip in constraint badges
        $skipKeys = ['items', 'properties', '$ref', 'definitions', 'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else', 'description'];

        // Other constraint badges
        /** @psalm-suppress MixedAssignment */
        foreach ($constraints as $key => $value) {
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

        // Example badge (shown last)
        if ($example !== '') {
            $badges[] = sprintf('<span class="badge example">example: %s</span>', htmlspecialchars($example));
        }

        $badgesHtml = implode("\n      ", $badges);

        return <<<HTML
<div class="extra-info">
      {$badgesHtml}
    </div>
HTML;
    }

    /**
     * @param array<string, HtmlObjectArray>                                                                                            $objects
     * @param array<string, array{embeds: array<array{rel: string, target: string}>, links: array<array{rel: string, target: string}>}> $objectRelations
     *
     * @return array{string, string}
     */
    private function renderObjectsAndArrays(array $objects, array $objectRelations): array
    {
        $objectsHtml = '';
        $arraysHtml = '';

        foreach ($objects as $name => $object) {
            if ($object['arrayItemType'] !== null) {
                $arraysHtml .= $this->renderArrayType($name, $object['arrayItemType']);
            } else {
                $objectsHtml .= $this->renderObject($name, $object, $objectRelations);
            }
        }

        return [$objectsHtml, $arraysHtml];
    }

    private function renderArrayType(string $name, string $itemType): string
    {
        $escapedName = htmlspecialchars($name);
        $itemTypeCapitalized = ucfirst($itemType);
        $itemLink = sprintf('<a href="#%s">%s</a>', htmlspecialchars($itemTypeCapitalized), htmlspecialchars($itemTypeCapitalized));

        return <<<HTML
<div class="object-section" id="{$escapedName}">
<h3 class="object-name">{$escapedName}</h3>
<p class="array-type">array of {$itemLink}</p>
</div>

HTML;
    }

    /**
     * @param HtmlObjectArray                                                                                                           $object
     * @param array<string, array{embeds: array<array{rel: string, target: string}>, links: array<array{rel: string, target: string}>}> $objectRelations
     */
    private function renderObject(string $name, array $object, array $objectRelations): string
    {
        $escapedName = htmlspecialchars($name);
        $rows = '';

        // Render properties
        foreach ($object['properties'] as $prop) {
            $rows .= $this->renderPropertyRow($prop);
        }

        // Render embeds
        if (isset($objectRelations[$name]['embeds'])) {
            foreach ($objectRelations[$name]['embeds'] as $embed) {
                $rows .= $this->renderRelationRow($embed, 'embed');
            }
        }

        // Render links
        if (isset($objectRelations[$name]['links'])) {
            foreach ($objectRelations[$name]['links'] as $link) {
                $rows .= $this->renderRelationRow($link, 'link');
            }
        }

        return <<<HTML
<div class="object-section" id="{$escapedName}">
<h3 class="object-name">{$escapedName}</h3>
<table>
<thead>
<tr><th>Name</th><th>Description</th><th>Meta</th></tr>
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
        $descriptionHtml = htmlspecialchars($prop['description']);

        return <<<HTML
<tr>
  <td>{$nameHtml}</td>
  <td>{$descriptionHtml}</td>
  <td>{$metaHtml}</td>
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
        $typeClass = 'type-' . (preg_replace('/[^a-zA-Z0-9-]/', '', $type) ?? $type);
        $badges[] = sprintf('<span class="badge %s">%s</span>', $typeClass, htmlspecialchars($type));

        // Format badge
        if ($prop['format'] !== null) {
            $badges[] = sprintf('<span class="badge format">format: %s</span>', htmlspecialchars($prop['format']));
        }

        // Keys to skip in constraint badges
        $skipKeys = ['items', 'properties', '$ref', 'definitions', 'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else', 'description'];

        // Constraint badges
        /** @psalm-suppress MixedAssignment */
        foreach ($prop['constraints'] as $key => $value) {
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

        // Example badge (shown last)
        if ($prop['example'] !== '' && $prop['example'] !== null) {
            $badges[] = sprintf('<span class="badge example">example: %s</span>', htmlspecialchars($prop['example']));
        }

        $badgesHtml = implode("\n      ", $badges);

        return <<<HTML
<div class="extra-info">
      {$badgesHtml}
    </div>
HTML;
    }

    /**
     * @param array{rel: string, target: string} $relation
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
</tr>

HTML;
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'boolean' => 'bool',
            default => $type,
        };
    }

    private function convertMarkdownLinks(string $text): string
    {
        $escaped = htmlspecialchars($text);

        // Convert markdown links [text](url) to HTML links
        return (string) preg_replace(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            '<a href="$2">$1</a>',
            $escaped,
        );
    }

    /**
     * @param array<array{rel: string, href: string}> $links
     */
    private function renderLinks(array $links): string
    {
        if ($links === []) {
            return '';
        }

        $items = [];
        foreach ($links as $link) {
            $rel = htmlspecialchars($link['rel']);
            $href = htmlspecialchars($link['href']);
            $items[] = sprintf('<a href="%s">%s</a>', $href, $rel);
        }

        return '<p class="doc-links">' . implode(' | ', $items) . '</p>';
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
/* Method cell */
.method-cell{min-width:200px;}
.method-title{font-size:0.85em;color:#24292f;font-weight:500;margin-top:4px;}
.method-desc{font-size:0.8em;color:#57606a;margin-top:2px;}
.method-alps{font-size:0.75em;margin-top:4px;}
.alps-link{color:#8957e5;text-decoration:none;font-family:'SFMono-Regular',Consolas,monospace;}
.alps-link:hover{text-decoration:underline;}
/* Table */
table{width:100%;border-collapse:collapse;margin:20px 0;}
th,td{padding:6px 10px;border:1px solid #ddd;text-align:left;vertical-align:top;}
th{background:#f6f8fa;font-weight:600;}
tr:hover{background-color:#f5f5f5;}
/* Path cell */
.path-cell{font-family:'SFMono-Regular',Consolas,monospace;font-weight:600;}
/* Param */
.param{font-family:'SFMono-Regular',Consolas,monospace;}
.alps-param{color:#8957e5;text-decoration:none;}
.alps-param:hover{text-decoration:underline;}
.req{color:#cf222e;}
.param-desc{font-size:0.85em;color:#57606a;}
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
.badge.example{background:#FFFBEB;border-color:#FDE68A;color:#92400E;font-family:'SFMono-Regular',Consolas,monospace;}
/* Sticky rows */
.embed-row{background:linear-gradient(135deg,#FFFBEB 0%,#FEF3C7 100%);box-shadow:3px 3px 6px rgba(0,0,0,0.15);border-left:3px solid #F59E0B;}
.link-row{background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 100%);box-shadow:3px 3px 6px rgba(0,0,0,0.15);border-left:3px solid #3B82F6;}
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
