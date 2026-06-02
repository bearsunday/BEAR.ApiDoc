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
        $legend = $matchedAlpsDescriptorCount > 0
            ? '<p class="legend">' . $this->alpsMark() . ' = defined in <a href="http://alps.io/">ALPS</a></p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Term Usage Index</title>
<link rel="profile" href="https://bearsunday.github.io/BEAR.ApiDoc/alps/apidoc.xml">
<style>
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
}
code {
    padding: 0.15em 0.35em;
    border-radius: 4px;
    background: #f6f8fa;
    font-family: ui-monospace, SFMono-Regular, SFMono, Menlo, Consolas, monospace;
}
.summary {
    padding-left: 1.4em;
}
.term {
    border-top: 1px solid #d8dee4;
    padding: 16px 0;
}
.term h3 {
    margin: 0 0 8px;
}
.descriptor,
.usages {
    margin: 8px 0;
}
.alps {
    margin-left: 6px;
    color: #1a7f37;
    font-size: 0.9em;
}
.legend {
    margin: 0 0 8px;
    color: #57606a;
    font-size: 0.9em;
}
</style>
</head>
<body>
<main>
<p><a href="index.html">API Documentation</a></p>
<h1>Term Usage Index</h1>
<p>This index reports lexical identifier matches only; it does not prove semantic equivalence.</p>

<h2>Summary</h2>
<ul class="summary">
  <li>Terms used in API: {$this->html((string) \count($apiUsages))}</li>
  <li>Terms with same-name ALPS descriptor: {$this->html((string) $matchedAlpsDescriptorCount)}</li>
  <li>Lexical ALPS coverage: {$this->html($coverage)}%</li>
  <li>Reserved representation fields: {$this->html((string) \count($reservedUsages))}</li>
</ul>

<h2>Terms</h2>
{$legend}
{$termsHtml}
{$reservedHtml}
</main>
</body>
</html>
HTML;
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

        $html = [];
        foreach ($apiUsages as $term => $usages) {
            $descriptor = $alpsDescriptors[$term] ?? null;
            $badge = $descriptor !== null ? $this->alpsMark() : '';
            $html[] = sprintf(
                '<section class="term" id="%s"><h3><code>%s</code>%s</h3>%s%s</section>',
                $this->htmlId('term', $term),
                $this->html($term),
                $badge,
                $this->renderDescriptor($descriptor),
                $this->renderUsageList($usages),
            );
        }

        return implode(PHP_EOL, $html);
    }

    /** @param array<string, array<string, true>> $reservedUsages */
    private function renderReservedTerms(array $reservedUsages): string
    {
        if ($reservedUsages === []) {
            return '';
        }

        $html = [
            '<h2>Reserved Representation Fields</h2>',
            '<p>Leading-underscore fields are listed separately because they usually belong to the representation format rather than the API domain vocabulary.</p>',
        ];
        foreach ($reservedUsages as $term => $usages) {
            $html[] = sprintf(
                '<section class="term" id="%s"><h3>Field: <code>%s</code></h3>%s</section>',
                $this->htmlId('field', $term),
                $this->html($term),
                $this->renderUsageList($usages),
            );
        }

        return implode(PHP_EOL, $html);
    }

    /** @param AlpsDescriptor|null $descriptor */
    private function renderDescriptor(?array $descriptor): string
    {
        if ($descriptor === null) {
            return '';
        }

        $items = [];
        foreach (['title', 'def', 'doc'] as $field) {
            $value = $descriptor[$field] ?? '';
            if ($value === '') {
                continue;
            }

            $items[] = sprintf('<li>%s: %s</li>', $this->html($field), $this->renderDescriptorValue($field, $value));
        }

        if ($items === []) {
            return '';
        }

        return sprintf('<ul class="descriptor">%s</ul>', implode('', $items));
    }

    private function renderDescriptorValue(string $field, string $value): string
    {
        $escapedValue = $this->html($value);
        if ($field === 'def' && str_starts_with($value, 'http')) {
            return sprintf('<a href="%s">%s</a>', $escapedValue, $escapedValue);
        }

        return $escapedValue;
    }

    /** @param array<string, true> $usages */
    private function renderUsageList(array $usages): string
    {
        $items = [];
        foreach (array_keys($usages) as $usage) {
            $items[] = sprintf('<li>%s</li>', $this->html($usage));
        }

        return sprintf('<ul class="usages">%s</ul>', implode('', $items));
    }

    private function alpsMark(): string
    {
        return '<span class="alps" title="defined in ALPS">&#x2611;</span>';
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
