<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use SimpleXMLElement;

use function array_is_list;
use function is_array;
use function is_file;
use function is_string;
use function pathinfo;
use function simplexml_load_file;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * Reads root-level `link` elements from an ALPS profile (JSON or XML); links
 * nested inside descriptors are not visited.
 *
 * @internal
 * @psalm-import-type DocLink from Types
 */
final class AlpsProfileLinkExtractor
{
    public function __construct(
        private readonly JsonFile $jsonFile,
    ) {
    }

    /** @return array<DocLink> */
    public function __invoke(string $alpsFile): array
    {
        if ($alpsFile === '' || $alpsFile === '0' || ! is_file($alpsFile)) {
            return [];
        }

        return strtolower(pathinfo($alpsFile, PATHINFO_EXTENSION)) === 'xml'
            ? $this->fromXml($alpsFile)
            : $this->fromJson($alpsFile);
    }

    /** @return array<DocLink> */
    private function fromJson(string $file): array
    {
        $decoded = $this->jsonFile->assoc($file);
        /** @var mixed $alps */
        $alps = $decoded['alps'] ?? $decoded;
        if (! is_array($alps) || ! isset($alps['link']) || ! is_array($alps['link'])) {
            return [];
        }

        /** @var array<array-key, mixed> $rawLinks */
        $rawLinks = array_is_list($alps['link']) ? $alps['link'] : [$alps['link']];

        $links = [];
        foreach ($rawLinks as $rawLink) {
            if (! is_array($rawLink)) {
                continue;
            }

            $link = $this->toLinkFromJson($rawLink);
            if ($link !== null) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * @param array<array-key, mixed> $rawLink
     *
     * @return DocLink|null
     */
    private function toLinkFromJson(array $rawLink): array|null
    {
        $rel = $rawLink['rel'] ?? null;
        $href = $rawLink['href'] ?? null;
        if (! is_string($rel) || $rel === '' || ! is_string($href) || $href === '') {
            return null;
        }

        /** @var mixed $title */
        $title = $rawLink['title'] ?? null;

        return is_string($title) && $title !== '' ? ['rel' => $rel, 'href' => $href, 'title' => $title] : ['rel' => $rel, 'href' => $href];
    }

    /** @return array<DocLink> */
    private function fromXml(string $file): array
    {
        $xml = @simplexml_load_file($file);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $links = [];
        $children = $xml->children();
        if ($children !== null) {
            /** @var SimpleXMLElement $element */
            foreach ($children as $element) {
                if ($element->getName() !== 'link') {
                    continue;
                }

                $link = $this->toLinkFromXml($element);
                if ($link !== null) {
                    $links[] = $link;
                }
            }
        }

        return $links;
    }

    /** @return DocLink|null */
    private function toLinkFromXml(SimpleXMLElement $element): array|null
    {
        $rel = (string) ($element['rel'] ?? '');
        $href = (string) ($element['href'] ?? '');
        if ($rel === '' || $href === '') {
            return null;
        }

        $title = (string) ($element['title'] ?? '');

        return $title !== '' ? ['rel' => $rel, 'href' => $href, 'title' => $title] : ['rel' => $rel, 'href' => $href];
    }
}
