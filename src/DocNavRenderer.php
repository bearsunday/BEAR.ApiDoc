<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function htmlspecialchars;
use function sprintf;

/**
 * Renders the top-of-page navigation block: ALPS profile root links grouped
 * by `tag`, followed by the apidoc.xml `<links>`.
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
        $untagged = [];
        /** @var array<array-key, array<AlpsProfileLink>> $tagged */
        $tagged = [];
        foreach ($alpsLinks as $link) {
            if ($link['tag'] === null) {
                $untagged[] = $link;
                continue;
            }

            $tagged[$link['tag']][] = $link;
        }

        // Untagged ALPS links and apidoc.xml links both render without a
        // heading, so they must come before the first tag heading: otherwise
        // they would visually read as members of the last tag group.
        $leadHtml = $this->renderAlpsLinkList($untagged) . $this->renderDocLinks($docLinks);
        $taggedHtml = $this->renderTaggedGroups($tagged);
        if ($leadHtml === '' && $taggedHtml === '') {
            return '';
        }

        return '<nav class="doc-nav">' . $leadHtml . $taggedHtml . '</nav>';
    }

    /**
     * One heading per tag, in first-appearance order.
     *
     * @param array<array-key, array<AlpsProfileLink>> $tagged
     */
    private function renderTaggedGroups(array $tagged): string
    {
        $html = '';
        foreach ($tagged as $tag => $tagLinks) {
            // PHP coerces numeric string keys (e.g. "2024") to int, so cast back.
            $html .= sprintf('<h3 class="doc-nav-tag">%s</h3>', htmlspecialchars((string) $tag));
            $html .= $this->renderAlpsLinkList($tagLinks);
        }

        return $html;
    }

    /** @param array<AlpsProfileLink> $links */
    private function renderAlpsLinkList(array $links): string
    {
        if ($links === []) {
            return '';
        }

        $items = '';
        foreach ($links as $link) {
            $href = htmlspecialchars($link['href']);
            $rel = htmlspecialchars($link['rel']);
            $items .= $link['title'] === null
                ? sprintf('<li><a href="%s">%s</a></li>', $href, $rel)
                : sprintf('<li><a href="%s">%s</a> <span class="doc-link-rel">%s</span></li>', $href, htmlspecialchars($link['title']), $rel);
        }

        return '<ul class="doc-links">' . $items . '</ul>';
    }

    /** @param array<DocLink> $links */
    private function renderDocLinks(array $links): string
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
