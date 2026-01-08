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
use function ltrim;
use function preg_match;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function ucfirst;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders HTML for API documentation
 *
 * @psalm-import-type HtmlParam from Types
 * @psalm-import-type HtmlMethod from Types
 * @psalm-import-type HtmlProperty from Types
 * @psalm-import-type HtmlObject from Types
 * @psalm-import-type HtmlRelation from Types
 * @psalm-import-type HtmlObjectRelations from Types
 * @psalm-import-type DocLink from Types
 */
final class HtmlRenderer
{
    private string $alpsHtmlPath = 'alps.html';

    /** @var array<string, string> Map of sanitized ID -> display name */
    private array $knownObjects = [];

    /**
     * @param array<string, array<string, HtmlMethod>> $endpoints
     * @param array<string, HtmlObject>                $objects
     * @param HtmlObjectRelations                      $objectRelations
     * @param array<DocLink>                           $links
     */
    public function render(
        string $title,
        string $description,
        array $endpoints,
        array $objects,
        array $objectRelations,
        array $links = [],
        string $alpsHtmlPath = 'alps.html',
        ?string $localCss = null,
    ): string {
        $this->alpsHtmlPath = $alpsHtmlPath;
        $escapedTitle = htmlspecialchars($title ?: 'API Documentation');
        $escapedDescription = $this->convertMarkdownLinks($description ?: '');
        $endpointsHtml = $this->renderEndpoints($endpoints);
        [$objectsHtml, $arraysHtml] = $this->renderObjectsAndArrays($objects, $objectRelations);
        $linksHtml = $this->renderLinks($links);

        $objectsSection = $objectsHtml !== '' ? '<h2>Objects</h2>
' . $objectsHtml : '';
        $arraysSection = $arraysHtml !== '' ? '<h2>Arrays</h2>
' . $arraysHtml : '';
        $linksSection = $linksHtml !== '' ? '<h2>Links</h2>
' . $linksHtml : '';

        $cssHtml = $localCss !== null
            ? sprintf('<style>%s</style>', $localCss)
            : '<link rel="stylesheet" href="https://bearsunday.github.io/BEAR.ApiDoc/apidoc.css">';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{$escapedTitle}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/3.0.1/github-markdown.min.css">
{$cssHtml}
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

    /** @param array<string, array<string, HtmlMethod>> $endpoints */
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
  <th>Type</th>
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

    /** @param array<string, HtmlMethod> $methods */
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
                $pathCell = $isFirstPath ? sprintf('<td rowspan="%d" class="path-cell" id="path-%s">%s</td>', $totalRows, htmlspecialchars(ltrim($path, '/')), htmlspecialchars($path)) : '';
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
                    $pathCell = sprintf('<td rowspan="%d" class="path-cell" id="path-%s">%s</td>', $totalRows, htmlspecialchars(ltrim($path, '/')), htmlspecialchars($path));
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

    /** @param array<string, HtmlMethod> $methods */
    private function countTotalRows(array $methods): int
    {
        $total = 0;
        foreach ($methods as $data) {
            $paramCount = count($data['params']);
            $total += $paramCount > 0 ? $paramCount : 1;
        }

        return $total;
    }

    /** @param array<string> $alps */
    private function renderMethodBadge(string $method, string $title = '', string $description = '', array $alps = []): string
    {
        $typeClass = match ($method) {
            'GET' => 'safe',
            'PUT', 'DELETE' => 'idempotent',
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
                $alpsLinks[] = sprintf('<a href="%s#%s" class="alps-link">%s</a>', $this->alpsHtmlPath, htmlspecialchars($alpsId), htmlspecialchars($alpsId));
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

        $sanitizedId = $this->sanitizeId($schemaName);

        return sprintf('<a href="#%s" class="schema-link">%s</a>', htmlspecialchars($sanitizedId), htmlspecialchars($schemaName));
    }

    /** @param HtmlParam $param */
    private function renderParamRow(string $pathCell, string $methodCell, array $param, string $responseCell): string
    {
        $nameHtml = $this->renderParamName($param['name'], $param['alps']);
        if ($param['required']) {
            $nameHtml .= '<span class="req">*</span>';
        }

        $typeHtml = $this->renderTypeBadge($param['type']);
        $metaHtml = $this->renderConstraintBadges($param['constraints'], $param['example']);
        $descriptionHtml = htmlspecialchars($param['description']);

        return <<<HTML
<tr>
  {$pathCell}
  {$methodCell}
  <td>{$nameHtml}</td>
  <td>{$typeHtml}</td>
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
            '<a href="%s#%s" class="param alps-param" title="%s">%s</a>',
            $this->alpsHtmlPath,
            $escapedName,
            htmlspecialchars($alpsTitle),
            $escapedName,
        );
    }

    private function renderTypeBadge(string $type): string
    {
        $typeClass = 'type-' . $type;

        return sprintf('<span class="badge %s">%s</span>', $typeClass, htmlspecialchars($type));
    }

    /** @param array<string, mixed> $constraints */
    private function renderConstraintBadges(array $constraints, string $example = ''): string
    {
        $badges = [];

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
     * @param array<string, HtmlObject> $objects
     * @param HtmlObjectRelations       $objectRelations
     *
     * @return array{string, string}
     */
    private function renderObjectsAndArrays(array $objects, array $objectRelations): array
    {
        // Collect known objects first (for relation linking)
        $this->knownObjects = [];
        foreach ($objects as $name => $object) {
            $sanitizedId = $this->sanitizeId($name);
            $this->knownObjects[$sanitizedId] = $name;
        }

        $objectsHtml = '';
        $arraysHtml = '';

        foreach ($objects as $name => $object) {
            if ($object['arrayItemType'] !== null) {
                $arraysHtml .= $this->renderArrayType($name, $object['arrayItemType'], $object['schemaFile']);
            } else {
                $objectsHtml .= $this->renderObject($name, $object, $objectRelations);
            }
        }

        return [$objectsHtml, $arraysHtml];
    }

    private function renderArrayType(string $name, string $itemType, ?string $schemaFile): string
    {
        $sanitizedId = $this->sanitizeId($name);
        $escapedName = htmlspecialchars($name);
        $itemTypeCapitalized = ucfirst($itemType);
        $itemTypeSanitized = $this->sanitizeId($itemTypeCapitalized);

        // Only link if item type is a known Object
        $itemDisplay = htmlspecialchars($itemTypeCapitalized);
        if (isset($this->knownObjects[$itemTypeSanitized])) {
            $itemDisplay = sprintf('<a href="#%s">%s</a>', htmlspecialchars($itemTypeSanitized), htmlspecialchars($itemTypeCapitalized));
        }

        // Schema file link
        $schemaLink = '';
        if ($schemaFile !== null) {
            $schemaLink = sprintf('<a href="schema/%s" class="schema-file-link" title="JSON Schema">📄</a> ', htmlspecialchars($schemaFile));
        }

        return <<<HTML
<div class="object-section" id="{$sanitizedId}">
<h3 class="object-name">{$schemaLink}{$escapedName}</h3>
<p class="array-type">array of {$itemDisplay}</p>
</div>

HTML;
    }

    /**
     * @param HtmlObject          $object
     * @param HtmlObjectRelations $objectRelations
     */
    private function renderObject(string $name, array $object, array $objectRelations): string
    {
        $sanitizedId = $this->sanitizeId($name);
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

        // Schema file link
        $schemaLink = '';
        if ($object['schemaFile'] !== null) {
            $schemaLink = sprintf('<a href="schema/%s" class="schema-file-link" title="JSON Schema">📄</a> ', htmlspecialchars($object['schemaFile']));
        }

        return <<<HTML
<div class="object-section" id="{$sanitizedId}">
<h3 class="object-name">{$schemaLink}{$escapedName}</h3>
<table>
<thead>
<tr><th>Name</th><th>Type</th><th>Description</th><th>Meta</th></tr>
</thead>
<tbody>
{$rows}
</tbody>
</table>
</div>

HTML;
    }

    /** @param HtmlProperty $prop */
    private function renderPropertyRow(array $prop): string
    {
        $escapedName = htmlspecialchars($prop['name']);
        // If property has a ref, make the name a link to the object
        if ($prop['ref'] !== null) {
            $sanitizedRef = $this->sanitizeId($prop['ref']);
            $nameHtml = sprintf('<a href="#%s" class="prop-name">%s</a>', htmlspecialchars($sanitizedRef), $escapedName);
        } else {
            $nameHtml = sprintf('<span class="prop-name">%s</span>', $escapedName);
        }

        $typeHtml = $this->renderPropertyTypeBadge($prop);
        $metaHtml = $this->renderPropertyMeta($prop);
        $descriptionHtml = htmlspecialchars($prop['description']);

        return <<<HTML
<tr>
  <td>{$nameHtml}</td>
  <td>{$typeHtml}</td>
  <td class="param-desc">{$descriptionHtml}</td>
  <td>{$metaHtml}</td>
</tr>

HTML;
    }

    /** @param HtmlProperty $prop */
    private function renderPropertyTypeBadge(array $prop): string
    {
        $type = $this->normalizeType($prop['type']);
        $typeClass = 'type-' . (preg_replace('/[^a-zA-Z0-9-]/', '', $type) ?? $type);

        return sprintf('<span class="badge %s">%s</span>', $typeClass, htmlspecialchars($type));
    }

    /** @param HtmlProperty $prop */
    private function renderPropertyMeta(array $prop): string
    {
        $badges = [];

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
     * @param HtmlRelation $relation
     *
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    private function renderRelationRow(array $relation, string $type): string
    {
        $rowClass = $type === 'embed' ? 'embed-row' : 'link-row';
        $rel = htmlspecialchars($relation['rel']);
        $href = htmlspecialchars($relation['href']);
        $title = $relation['title'] !== '' ? htmlspecialchars($relation['title']) : '';
        $transitionType = $type === 'embed' ? 'semantic' : $this->getTransitionType($relation['rel']);
        $indicator = $transitionType !== '' ? sprintf('<span class="ti %s"></span>', $transitionType) : '';
        $hrefLabel = $type === 'embed' ? 'src' : 'href';

        // Try to link to Object section - derive Object name from rel
        $relDisplay = $rel;
        $objectName = $relation['rel'];

        // Remove go/do prefix for links (e.g., goCard -> Card, doDelete -> Delete)
        if (preg_match('/^(go|do)([A-Z].*)$/', $objectName, $matches)) {
            $objectName = $matches[2];
            // Try singular form (e.g., Tickets -> Ticket)
            if (str_ends_with($objectName, 's') && strlen($objectName) > 1) {
                $singular = rtrim($objectName, 's');
                // Check if singular form exists first
                if (isset($this->knownObjects[$singular])) {
                    $objectName = $singular;
                }
            }
        } else {
            $objectName = ucfirst($objectName);
        }

        // Only create a link if the target object exists
        if (isset($this->knownObjects[$objectName])) {
            $relDisplay = sprintf('<a href="#%s">%s</a>', htmlspecialchars($objectName), $rel);
        }

        return <<<HTML
<tr class="{$rowClass}">
  <td class="prop-name">{$indicator}{$relDisplay}</td>
  <td><span class="badge {$type}">{$type}</span></td>
  <td class="param-desc">{$title}</td>
  <td>
    <div class="extra-info">
      <span class="badge href">{$hrefLabel}: {$href}</span>
    </div>
  </td>
</tr>

HTML;
    }

    private function getTransitionType(string $rel): string
    {
        if (str_starts_with($rel, 'go')) {
            return 'safe';
        }

        if (str_starts_with($rel, 'do')) {
            return 'unsafe';
        }

        return '';
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'boolean' => 'bool',
            default => $type,
        };
    }

    /**
     * Sanitize a name to be a valid HTML ID (remove spaces)
     */
    private function sanitizeId(string $name): string
    {
        // Remove spaces to create valid HTML ID (e.g., "Calendar Event" -> "CalendarEvent")
        return (string) preg_replace('/\s+/', '', $name);
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

    /** @param array<DocLink> $links */
    private function renderLinks(array $links): string
    {
        if ($links === []) {
            return '';
        }

        $items = '';
        foreach ($links as $link) {
            $rel = htmlspecialchars($link['rel']);
            $href = htmlspecialchars($link['href']);
            $items .= sprintf('<li><strong>%s</strong> : <a href="%s">%s</a></li>', $rel, $href, $href);
        }

        return '<ul class="doc-links">' . $items . '</ul>';
    }
}
