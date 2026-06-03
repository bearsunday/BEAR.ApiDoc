<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function array_keys;
use function htmlspecialchars;
use function implode;
use function preg_replace;
use function sha1;
use function sprintf;
use function str_starts_with;
use function substr;
use function trim;

use const PHP_EOL;

/** @psalm-import-type AlpsDescriptor from TermUsageIndex */
final readonly class TermUsageHtmlRenderer
{
    /**
     * @param array<string, array<string, true>> $apiUsages
     * @param array<string, array<string, true>> $reservedUsages
     * @param array<string, AlpsDescriptor>      $alpsDescriptors
     */
    public function render(
        array $apiUsages,
        array $reservedUsages,
        array $alpsDescriptors,
        int $matchedAlpsDescriptorCount,
        string $coverage,
    ): string {
        $termsHtml = $this->renderTerms($apiUsages, $alpsDescriptors);
        $reservedHtml = $this->renderReservedTerms($reservedUsages);
        $indexHtml = $this->renderIndex($apiUsages, $reservedUsages);
        $styles = $this->styles();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Term Usage Index</title>
<link rel="profile" href="https://bearsunday.github.io/BEAR.ApiDoc/alps/terms.xml">
<style>
{$styles}
</style>
</head>
<body>
<main class="termUsageIndex">
<p><a href="index.html">API Documentation</a></p>
<h1>Term Usage Index</h1>
<p>This index reports lexical identifier matches only; it does not prove semantic equivalence. Its own vocabulary (term, alpsBacked, usage, reservedField, coverage) is defined by the profile linked above. A term whose spelling also exists in the configured application ALPS profile is marked <code>alpsBacked</code>; the matched application descriptor id is carried on the entry's <code>data-alps</code> attribute.</p>

<h2>Summary</h2>
<ul>
  <li class="termsUsedCount">Terms used in API: {$this->html((string) \count($apiUsages))}</li>
  <li class="alpsMatchedCount">Terms with same-name ALPS descriptor: {$this->html((string) $matchedAlpsDescriptorCount)}</li>
  <li class="lexicalCoverage">Lexical ALPS coverage: {$this->html($coverage)}%</li>
  <li class="reservedCount">Reserved representation fields: {$this->html((string) \count($reservedUsages))}</li>
</ul>

{$indexHtml}
<h2>Terms</h2>
{$termsHtml}
{$reservedHtml}
</main>
</body>
</html>
HTML;
    }

    private function styles(): string
    {
        return <<<'CSS'
body {
    margin: 0;
    padding: 32px;
    color: #1f2328;
    background: #fff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    line-height: 1.5;
}
main {
    max-width: 980px;
}
a {
    color: #0969da;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
code {
    padding: 0.15em 0.35em;
    border-radius: 4px;
    background: #f6f8fa;
    font-family: ui-monospace, SFMono-Regular, SFMono, Menlo, Consolas, monospace;
}
dl {
    margin: 0;
}
dt {
    margin-top: 16px;
    border-top: 1px solid #d8dee4;
    padding-top: 16px;
}
dt code {
    font-size: 1.2em;
    font-weight: 600;
}
.mark {
    margin-left: 6px;
    color: #1a7f37;
}
dd {
    margin: 4px 0 0 16px;
}
dd p {
    margin: 0 0 4px;
    color: #57606a;
}
dd ul {
    margin: 4px 0;
    padding-left: 1.2em;
}
.index-list {
    column-width: 220px;
    column-gap: 24px;
    list-style-type: none;
    padding: 0;
    margin: 16px 0 32px;
}
.index-list li {
    padding: 4px 0;
    font-family: ui-monospace, SFMono-Regular, SFMono, Menlo, Consolas, monospace;
    font-size: 0.95em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
CSS;
    }

    /**
     * @param array<string, array<string, true>> $apiUsages
     * @param array<string, AlpsDescriptor>      $alpsDescriptors
     */
    private function renderTerms(array $apiUsages, array $alpsDescriptors): string
    {
        if ($apiUsages === []) {
            return '<p>No API terms found.</p>';
        }

        $entries = [];
        foreach ($apiUsages as $term => $usages) {
            $entries[] = $this->renderEntry('term', $term, $usages, $alpsDescriptors[$term] ?? null);
        }

        return sprintf('<dl>%s</dl>', PHP_EOL . implode(PHP_EOL, $entries) . PHP_EOL);
    }

    /** @param array<string, array<string, true>> $reservedUsages */
    private function renderReservedTerms(array $reservedUsages): string
    {
        if ($reservedUsages === []) {
            return '';
        }

        $entries = [];
        foreach ($reservedUsages as $term => $usages) {
            $entries[] = $this->renderEntry('field', $term, $usages, null);
        }

        return implode(PHP_EOL, [
            '<h2>Reserved Representation Fields</h2>',
            '<p>Leading-underscore fields are listed separately because they usually belong to the representation format rather than the API domain vocabulary.</p>',
            sprintf('<dl>%s</dl>', PHP_EOL . implode(PHP_EOL, $entries) . PHP_EOL),
        ]);
    }

    /**
     * @param array<string, array<string, true>> $apiUsages
     * @param array<string, array<string, true>> $reservedUsages
     */
    private function renderIndex(array $apiUsages, array $reservedUsages): string
    {
        $items = [];
        foreach (array_keys($apiUsages) as $term) {
            $items[] = $this->indexItem('term', $term);
        }

        foreach (array_keys($reservedUsages) as $term) {
            $items[] = $this->indexItem('field', $term);
        }

        if ($items === []) {
            return '';
        }

        return sprintf('<h2>Index</h2>%s<ul class="index-list">%s</ul>', PHP_EOL, implode('', $items));
    }

    private function indexItem(string $idPrefix, string $term): string
    {
        return sprintf('<li><a href="#%s">%s</a></li>', $this->htmlId($idPrefix, $term), $this->html($term));
    }

    /**
     * @param array<string, true> $usages
     * @param AlpsDescriptor|null $descriptor
     */
    private function renderEntry(string $idPrefix, string $term, array $usages, ?array $descriptor): string
    {
        $backed = $descriptor !== null;
        // Each entry binds to this index's own profile (terms.xml): `term` (or
        // `term alpsBacked`) for API terms, `reservedField` for representation
        // fields. The matched application descriptor id is a cross-reference, so
        // it rides on data-alps rather than masquerading as a profile class.
        $baseClass = $idPrefix === 'field' ? 'reservedField' : 'term';
        $class = $backed ? $baseClass . ' alpsBacked' : $baseClass;
        $dataAlps = $backed ? sprintf(' data-alps="%s"', $this->html($term)) : '';
        $mark = $backed ? '<span class="mark" title="same-name ALPS descriptor">&#x2611;</span>' : '';

        return sprintf(
            '<dt id="%s" class="%s"%s><code>%s</code>%s</dt>%s<dd>%s%s</dd>',
            $this->htmlId($idPrefix, $term),
            $class,
            $dataAlps,
            $this->html($term),
            $mark,
            PHP_EOL,
            $this->renderDescriptor($descriptor),
            $this->renderUsageList($usages),
        );
    }

    /** @param AlpsDescriptor|null $descriptor */
    private function renderDescriptor(?array $descriptor): string
    {
        if ($descriptor === null) {
            return '';
        }

        $lines = [];
        foreach (['title', 'def', 'doc'] as $field) {
            $value = $descriptor[$field] ?? '';
            if ($value === '') {
                continue;
            }

            $lines[] = sprintf('<p class="borrowedDescriptor">%s: %s</p>', $field, $this->renderDescriptorValue($field, $value));
        }

        return implode('', $lines);
    }

    private function renderDescriptorValue(string $field, string $value): string
    {
        $escaped = $this->html($value);
        if ($field === 'def' && str_starts_with($value, 'http')) {
            return sprintf('<a href="%s">%s</a>', $escaped, $escaped);
        }

        return $escaped;
    }

    /** @param array<string, true> $usages */
    private function renderUsageList(array $usages): string
    {
        $items = [];
        foreach (array_keys($usages) as $usage) {
            $items[] = sprintf('<li class="%s">%s</li>', $this->usageClass($usage), $this->html($usage));
        }

        return sprintf('<ul>%s</ul>', implode('', $items));
    }

    private function usageClass(string $usage): string
    {
        if (str_starts_with($usage, 'parameter:')) {
            return 'usage parameterUsage';
        }

        if (str_starts_with($usage, 'schema property:')) {
            return 'usage schemaPropertyUsage';
        }

        return 'usage';
    }

    private function htmlId(string $prefix, string $value): string
    {
        $id = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value), '-');
        if ($id === '') {
            $id = 'empty-' . substr(sha1($value), 0, 8);
        }

        return $prefix . '-' . $id;
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value);
    }
}
