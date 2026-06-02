<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function array_keys;
use function array_map;
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
dl {
    margin: 0;
}
dt {
    margin-top: 16px;
    border-top: 1px solid #d8dee4;
    padding-top: 16px;
}
dd {
    margin: 4px 0 0;
}
dd p {
    margin: 0 0 4px;
    color: #57606a;
}
dd ul {
    margin: 4px 0;
}
</style>
</head>
<body>
<main>
<p><a href="index.html">API Documentation</a></p>
<h1>Term Usage Index</h1>
<p>This index reports lexical identifier matches only; it does not prove semantic equivalence. A term backed by an ALPS descriptor carries that descriptor as its class, per the profile linked above.</p>

<h2>Summary</h2>
<ul>
  <li>Terms used in API: {$this->html((string) \count($apiUsages))}</li>
  <li>Terms with same-name ALPS descriptor: {$this->html((string) $matchedAlpsDescriptorCount)}</li>
  <li>Lexical ALPS coverage: {$this->html($coverage)}%</li>
  <li>Reserved representation fields: {$this->html((string) \count($reservedUsages))}</li>
</ul>

<h2>Terms</h2>
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
     * @param array<string, true> $usages
     * @param AlpsDescriptor|null $descriptor
     */
    private function renderEntry(string $idPrefix, string $term, array $usages, ?array $descriptor): string
    {
        $def = $descriptor !== null ? ($descriptor['def'] ?? '') : '';
        $defIsUrl = $def !== '' && str_starts_with($def, 'http');

        $code = sprintf('<code>%s</code>', $this->html($term));
        $name = $defIsUrl ? sprintf('<a href="%s">%s</a>', $this->html($def), $code) : $code;
        $classAttr = $descriptor !== null ? sprintf(' class="%s"', $this->html($term)) : '';

        return sprintf(
            '<dt id="%s"%s>%s</dt>%s<dd>%s%s</dd>',
            $this->htmlId($idPrefix, $term),
            $classAttr,
            $name,
            PHP_EOL,
            $this->renderDescription($descriptor, $defIsUrl),
            $this->renderUsageList($usages),
        );
    }

    /** @param AlpsDescriptor|null $descriptor */
    private function renderDescription(?array $descriptor, bool $defIsUrl): string
    {
        if ($descriptor === null) {
            return '';
        }

        $lines = [];
        foreach (['title', 'doc'] as $field) {
            $value = $descriptor[$field] ?? '';
            if ($value !== '') {
                $lines[] = $this->html($value);
            }
        }

        $def = $descriptor['def'] ?? '';
        if ($def !== '' && ! $defIsUrl) {
            $lines[] = $this->html($def);
        }

        if ($lines === []) {
            return '';
        }

        return implode('', array_map(static fn (string $line): string => sprintf('<p>%s</p>', $line), $lines));
    }

    /** @param array<string, true> $usages */
    private function renderUsageList(array $usages): string
    {
        $items = [];
        foreach (array_keys($usages) as $usage) {
            $items[] = sprintf('<li>%s</li>', $this->html($usage));
        }

        return sprintf('<ul>%s</ul>', implode('', $items));
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
