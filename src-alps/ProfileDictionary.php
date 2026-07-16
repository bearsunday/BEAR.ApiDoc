<?php

declare(strict_types=1);

namespace AlpsAsd\AlpsProfile;

use AlpsAsd\AlpsProfile\Exception\InvalidProfileException;
use ArrayObject;
use JsonException;
use SimpleXMLElement;

use function array_key_exists;
use function array_values;
use function dirname;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function pathinfo;
use function realpath;
use function simplexml_load_file;
use function sprintf;
use function str_contains;
use function strpos;
use function strtolower;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_EXTENSION;

/**
 * Reads ALPS profile descriptors into an id => title/doc/def dictionary.
 *
 * This intentionally contains only the small profile-reading surface needed by
 * BEAR.ApiDoc. Diagram rendering belongs to the JavaScript ASD package.
 *
 * External descriptor references (`{"href": "common.json#id"}`) and transition
 * targets (`"rt": "common.json#id"`) are resolved by loading the referenced
 * file so that profiles split across files keep their labels. Remote references
 * (`http://`, `https://`) are not fetched; their labels fall back to the
 * descriptor id. Cross-file references are resolved in a single direction and a
 * per-file loop guard keeps cyclic references from recursing infinitely: a file
 * that is still being read contributes no labels back to the file referencing
 * it. Invalid or unreadable local profiles fail loudly with
 * {@see InvalidProfileException}.
 */
final readonly class ProfileDictionary
{
    /** @param array<string, string> $dictionary */
    private function __construct(
        private array $dictionary,
    ) {
    }

    public static function fromFile(string $file): self
    {
        $cache = [];

        return new self(self::dictForFile($file, $cache));
    }

    public static function fromJsonFile(string $file): self
    {
        $cache = [];

        return new self(self::dictForParsedFile($file, 'json', $cache));
    }

    public static function fromXmlFile(string $file): self
    {
        $cache = [];

        return new self(self::dictForParsedFile($file, 'xml', $cache));
    }

    /**
     * @return ArrayObject<string, string>
     *
     * @psalm-suppress MixedMethodCall ArrayObject is intentionally used by BEAR.ApiDoc's existing API surface.
     */
    public function toArrayObject(): ArrayObject
    {
        /** @var ArrayObject<string, string> $dictionary */
        $dictionary = new ArrayObject($this->dictionary);

        return $dictionary;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->dictionary;
    }

    /**
     * @param array<string, array<string, string>|null> $cache
     *
     * @return array<string, string>
     */
    private static function dictForFile(string $file, array &$cache): array
    {
        $format = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xml' ? 'xml' : 'json';

        return self::dictForParsedFile($file, $format, $cache);
    }

    /**
     * @param array<string, array<string, string>|null> $cache realpath => dictionary (null while being built, as a loop guard)
     *
     * @return array<string, string>
     */
    private static function dictForParsedFile(string $file, string $format, array &$cache): array
    {
        $key = self::cacheKey($file);
        if (array_key_exists($key, $cache)) {
            return $cache[$key] ?? [];
        }

        $cache[$key] = null;
        $dictionary = $format === 'json'
            ? self::collectJson($file, $cache)
            : self::collectXml($file, $cache);
        $cache[$key] = $dictionary;

        return $dictionary;
    }

    /**
     * @param array<string, array<string, string>|null> $cache
     *
     * @return array<string, string>
     */
    private static function collectJson(string $file, array &$cache): array
    {
        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new InvalidProfileException(sprintf('Cannot read ALPS profile: %s', $file));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidProfileException(sprintf('Invalid JSON ALPS profile: %s', $file), 0, $e);
        }

        if (! is_array($decoded)) {
            throw new InvalidProfileException(sprintf('Invalid ALPS profile: %s', $file));
        }

        $dictionary = [];
        self::walkJsonDescriptors(self::jsonDescriptorList($decoded), dirname($file), $dictionary, $cache);

        return $dictionary;
    }

    /**
     * @param array<array-key, mixed> $decoded
     *
     * @return array<array-key, mixed>
     */
    private static function jsonDescriptorList(array $decoded): array
    {
        /** @var mixed $alps */
        $alps = $decoded['alps'] ?? null;
        if (is_array($alps) && isset($alps['descriptor']) && is_array($alps['descriptor'])) {
            return array_values($alps['descriptor']);
        }

        if (isset($decoded['descriptor']) && is_array($decoded['descriptor'])) {
            return array_values($decoded['descriptor']);
        }

        return [];
    }

    /**
     * @param array<array-key, mixed>                   $descriptors
     * @param array<string, string>                     $dictionary
     * @param array<string, array<string, string>|null> $cache
     */
    private static function walkJsonDescriptors(array $descriptors, string $baseDir, array &$dictionary, array &$cache): void
    {
        foreach ($descriptors as $descriptor) {
            if (! is_array($descriptor)) {
                continue;
            }

            if (isset($descriptor['id']) && is_string($descriptor['id'])) {
                $dictionary[$descriptor['id']] = self::titleFromJsonDescriptor($descriptor);
                self::resolveTarget($descriptor['rt'] ?? null, $baseDir, $dictionary, $cache);

                /** @var mixed $nested */
                $nested = $descriptor['descriptor'] ?? null;
                if (is_array($nested)) {
                    self::walkJsonDescriptors(array_values($nested), $baseDir, $dictionary, $cache);
                }

                continue;
            }

            if (isset($descriptor['href']) && is_string($descriptor['href'])) {
                self::resolveHref($descriptor['href'], $baseDir, $dictionary, $cache);
            }
        }
    }

    /** @param array<array-key, mixed> $node */
    private static function titleFromJsonDescriptor(array $node): string
    {
        if (isset($node['title']) && is_string($node['title']) && $node['title'] !== '') {
            return $node['title'];
        }

        $doc = self::docFromJson($node['doc'] ?? null);
        if ($doc !== '') {
            return $doc;
        }

        foreach (['def', 'ref', 'src'] as $key) {
            if (isset($node[$key]) && is_string($node[$key]) && $node[$key] !== '') {
                return sprintf('[%s](%s)', $node[$key], $node[$key]);
            }
        }

        return '';
    }

    private static function docFromJson(mixed $doc): string
    {
        if (is_string($doc)) {
            return trim($doc);
        }

        if (is_array($doc) && isset($doc['value']) && is_string($doc['value'])) {
            return trim($doc['value']);
        }

        return '';
    }

    /**
     * @param array<string, array<string, string>|null> $cache
     *
     * @return array<string, string>
     */
    private static function collectXml(string $file, array &$cache): array
    {
        $xml = @simplexml_load_file($file);
        if (! $xml instanceof SimpleXMLElement) {
            throw new InvalidProfileException(sprintf('Invalid XML ALPS profile: %s', $file));
        }

        $dictionary = [];
        self::walkXmlDescriptors($xml, dirname($file), $dictionary, $cache);

        return $dictionary;
    }

    /**
     * @param array<string, string>                     $dictionary
     * @param array<string, array<string, string>|null> $cache
     */
    private static function walkXmlDescriptors(SimpleXMLElement $node, string $baseDir, array &$dictionary, array &$cache): void
    {
        foreach ($node->children() ?? [] as $child) {
            /** @var SimpleXMLElement $child */
            if ($child->getName() !== 'descriptor') {
                continue;
            }

            $id = (string) $child['id'];
            if ($id !== '') {
                $dictionary[$id] = self::titleFromXmlDescriptor($child);
                self::resolveTarget((string) $child['rt'], $baseDir, $dictionary, $cache);
                self::walkXmlDescriptors($child, $baseDir, $dictionary, $cache);

                continue;
            }

            $href = (string) $child['href'];
            if ($href !== '') {
                self::resolveHref($href, $baseDir, $dictionary, $cache);
            }
        }
    }

    private static function titleFromXmlDescriptor(SimpleXMLElement $node): string
    {
        $title = (string) $node['title'];
        if ($title !== '') {
            return $title;
        }

        foreach ($node->children() ?? [] as $child) {
            /** @var SimpleXMLElement $child */
            if ($child->getName() !== 'doc') {
                continue;
            }

            $value = trim((string) $child);
            if ($value !== '') {
                return $value;
            }

            $attributeValue = trim((string) $child['value']);
            if ($attributeValue !== '') {
                return $attributeValue;
            }
        }

        foreach (['def', 'ref', 'src'] as $attribute) {
            $value = (string) $node[$attribute];
            if ($value !== '') {
                return sprintf('[%s](%s)', $value, $value);
            }
        }

        return '';
    }

    /**
     * Resolve an external descriptor reference (`file#id`) into the dictionary.
     *
     * Internal references (`#id`) are ignored because the target descriptor is
     * collected from its inline definition in the same file.
     *
     * @param array<string, string>                     $dictionary
     * @param array<string, array<string, string>|null> $cache
     */
    private static function resolveHref(string $href, string $baseDir, array &$dictionary, array &$cache): void
    {
        self::resolveTarget($href, $baseDir, $dictionary, $cache);
    }

    /**
     * @param array<string, string>                     $dictionary
     * @param array<string, array<string, string>|null> $cache
     */
    private static function resolveTarget(mixed $target, string $baseDir, array &$dictionary, array &$cache): void
    {
        if (! is_string($target) || $target === '') {
            return;
        }

        $hashPosition = strpos($target, '#');
        if ($hashPosition === false) {
            return;
        }

        $filePart = substr($target, 0, $hashPosition);
        $id = substr($target, $hashPosition + 1);
        if ($filePart === '' || $id === '') {
            return;
        }

        // Remote profiles are not fetched: this avoids network access (SSRF) and
        // offline failures. Such references fall back to the descriptor id.
        if (str_contains($filePart, '://')) {
            return;
        }

        $external = self::dictForFile(self::resolvePath($filePart, $baseDir), $cache);
        if (isset($external[$id]) && ! isset($dictionary[$id])) {
            $dictionary[$id] = $external[$id];
        }
    }

    private static function resolvePath(string $filePart, string $baseDir): string
    {
        if ($filePart[0] === '/') {
            return $filePart;
        }

        return $baseDir . DIRECTORY_SEPARATOR . $filePart;
    }

    private static function cacheKey(string $file): string
    {
        $real = realpath($file);

        return $real === false ? $file : $real;
    }
}
