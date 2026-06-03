<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function htmlspecialchars;
use function implode;
use function preg_replace;
use function sha1;
use function sprintf;
use function substr;
use function trim;

use const PHP_EOL;

/**
 * @psalm-import-type AuditFindingGroup from ApiDocAudit
 * @psalm-import-type AuditSummary from ApiDocAudit
 */
final readonly class AuditHtmlRenderer
{
    /**
     * @param AuditSummary            $summary
     * @param list<AuditFindingGroup> $groups
     */
    public function render(array $summary, array $groups): string
    {
        $summaryHtml = $this->renderSummary($summary);
        $findingsHtml = $this->renderFindings($groups);
        $styles = $this->styles();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Documentation Audit</title>
<link rel="profile" href="https://bearsunday.github.io/BEAR.ApiDoc/alps/audit.xml">
<style>
{$styles}
</style>
</head>
<body>
<main class="apiDocumentationAudit">
<h1>API Documentation Audit</h1>
<p>This report lists missing documentation per operation. It describes the documentation's structure, not the API's domain meaning, per the profile linked above.</p>

<h2>Summary</h2>
{$summaryHtml}

<h2>Findings</h2>
{$findingsHtml}
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
.operation {
    margin-top: 16px;
    border-top: 1px solid #d8dee4;
    padding-top: 8px;
}
.operation h3 {
    font-size: 1.05em;
    font-family: ui-monospace, SFMono-Regular, SFMono, Menlo, Consolas, monospace;
}
.operation ul {
    margin: 4px 0;
    padding-left: 1.2em;
}
.findingType {
    color: #57606a;
}
CSS;
    }

    /** @param AuditSummary $summary */
    private function renderSummary(array $summary): string
    {
        $items = [
            sprintf('<li class="resourceCount">Resources: %s</li>', $this->html((string) $summary['resourceCount'])),
            sprintf('<li class="operationCount">Operations: %s</li>', $this->html((string) $summary['operationCount'])),
            sprintf('<li class="responseSchemaCount">Operations with response schema: %s</li>', $this->html((string) $summary['responseSchemaCount'])),
            sprintf('<li class="requestSchemaCount">Operations with request schema: %s</li>', $this->html((string) $summary['requestSchemaCount'])),
        ];

        if ($summary['alpsAttributeCount'] !== null) {
            $items[] = sprintf('<li class="alpsAttributeCount">Operations with ALPS attributes: %s</li>', $this->html((string) $summary['alpsAttributeCount']));
        }

        return sprintf('<ul>%s</ul>', implode('', $items));
    }

    /** @param list<AuditFindingGroup> $groups */
    private function renderFindings(array $groups): string
    {
        if ($groups === []) {
            return '<p>No documentation gaps found.</p>';
        }

        $sections = [];
        foreach ($groups as $group) {
            $sections[] = $this->renderGroup($group);
        }

        return implode(PHP_EOL, $sections);
    }

    /** @param AuditFindingGroup $group */
    private function renderGroup(array $group): string
    {
        $items = [];
        foreach ($group['items'] as $finding) {
            $items[] = sprintf(
                '<li class="finding"><code class="findingType">%s</code> %s</li>',
                $this->html($finding['type']),
                $this->html($finding['message']),
            );
        }

        return sprintf(
            '<section class="operation" id="%s"><h3>%s %s</h3><ul>%s</ul></section>',
            $this->htmlId($group['method'], $group['path']),
            $this->html($group['method']),
            $this->html($group['path']),
            implode('', $items),
        );
    }

    private function htmlId(string $method, string $path): string
    {
        $raw = $method . ' ' . $path;
        // A readable slug plus a short stable hash of the raw method+path: distinct
        // operations whose slugs would collide (e.g. "/foo-bar" vs "/foo/bar", both
        // slugging to "op-GET-foo-bar") still get distinct, deterministic ids.
        $slug = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $raw), '-');

        return sprintf('op-%s-%s', $slug, substr(sha1($raw), 0, 7));
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value);
    }
}
