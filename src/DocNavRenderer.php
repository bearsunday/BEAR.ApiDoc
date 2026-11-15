<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function htmlspecialchars;
use function implode;
use function sprintf;

/**
 * Renders the top-of-page navigation: one row of untagged ALPS profile links and
 * apidoc.xml `<links>`, then one labelled row per ALPS `tag` in first-appearance order.
 *
 * @internal
 * @psalm-import-type AlpsProfileLink from Types
 * @psalm-import-type DocLink from Types
 */
final class DocNavRenderer
{
    /**
     * @param array<AlpsProfileLink> $alpsLinks
     * @param array<DocLink>         $docLinks
     */
    public function __invoke(array $alpsLinks, array $docLinks): string
    {
        $lead = [];
        /** @var array<array-key, list<string>> $tagged */
        $tagged = [];
        foreach ($alpsLinks as $link) {
            $anchor = $this->anchor($link['href'], $link['title'] ?? $link['rel'], $link['rel']);
            if ($link['tag'] === null) {
                $lead[] = $anchor;
                continue;
            }

            $tagged[$link['tag']][] = $anchor;
        }

        foreach ($docLinks as $link) {
            $lead[] = $this->anchor($link['href'], $link['rel'], $link['rel']);
        }

        $rows = $lead === [] ? '' : $this->row('', $lead);
        foreach ($tagged as $tag => $anchors) {
            // A numeric tag such as "2024" comes back from the array key as int
            $rows .= $this->row((string) $tag, $anchors);
        }

        return $rows === '' ? '' : '<nav class="doc-nav">' . $rows . '</nav>';
    }

    private function anchor(string $href, string $text, string $rel): string
    {
        return sprintf(
            '<a href="%s" rel="%s" title="%s">%s</a>',
            htmlspecialchars($href),
            htmlspecialchars($rel),
            htmlspecialchars($rel),
            htmlspecialchars($text),
        );
    }

    /** @param list<string> $anchors */
    private function row(string $tag, array $anchors): string
    {
        // The untagged row keeps an empty label so its links line up with the tagged rows
        return sprintf(
            '<div class="doc-nav-row"><span class="doc-nav-tag">%s</span><span class="doc-nav-links">%s</span></div>',
            htmlspecialchars($tag),
            implode(' ', $anchors),
        );
    }
}
