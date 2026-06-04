<?php

declare(strict_types=1);

namespace AlpsAsd\AlpsProfile;

use ArrayObject;
use JsonException;
use SimpleXMLElement;

use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function pathinfo;
use function sprintf;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PATHINFO_EXTENSION;

/**
 * Reads ALPS profile descriptors into an id => title/doc/def dictionary.
 *
 * This intentionally contains only the small profile-reading surface needed by
 * BEAR.ApiDoc. Diagram rendering belongs to the JavaScript ASD package.
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
        return match (pathinfo($file, PATHINFO_EXTENSION)) {
            'json' => self::fromJsonFile($file),
            default => self::fromXmlFile($file),
        };
    }

    public static function fromJsonFile(string $file): self
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return new self([]);
        }

        try {
            $profile = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new self([]);
        }

        if (! is_array($profile)) {
            return new self([]);
        }

        $dictionary = [];
        self::collectJsonDescriptors($profile, $dictionary);

        return new self($dictionary);
    }

    public static function fromXmlFile(string $file): self
    {
        $xml = @simplexml_load_file($file);
        if (! $xml instanceof SimpleXMLElement) {
            return new self([]);
        }

        $dictionary = [];
        self::collectXmlDescriptors($xml, $dictionary);

        return new self($dictionary);
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
     * @param array<array-key, mixed> $node
     * @param array<string, string>   $dictionary
     */
    private static function collectJsonDescriptors(array $node, array &$dictionary): void
    {
        if (isset($node['id']) && is_string($node['id'])) {
            $dictionary[$node['id']] = self::titleFromJsonDescriptor($node);
        }

        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }

            self::collectJsonDescriptors($value, $dictionary);
        }
    }

    /**
     * @param array<array-key, mixed> $node
     */
    private static function titleFromJsonDescriptor(array $node): string
    {
        if (isset($node['title']) && is_string($node['title']) && $node['title'] !== '') {
            return $node['title'];
        }

        $doc = self::docFromJson($node['doc'] ?? null);
        if ($doc !== '') {
            return $doc;
        }

        if (isset($node['def']) && is_string($node['def']) && $node['def'] !== '') {
            return sprintf('[%s](%s)', $node['def'], $node['def']);
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

    /** @param array<string, string> $dictionary */
    private static function collectXmlDescriptors(SimpleXMLElement $node, array &$dictionary): void
    {
        $id = (string) $node['id'];
        if ($id !== '') {
            $dictionary[$id] = self::titleFromXmlDescriptor($node);
        }

        foreach ($node->children() ?? [] as $child) {
            /** @var SimpleXMLElement $child */
            if ($child->getName() !== 'descriptor') {
                continue;
            }

            self::collectXmlDescriptors($child, $dictionary);
        }
    }

    private static function titleFromXmlDescriptor(SimpleXMLElement $node): string
    {
        $title = (string) $node['title'];
        if ($title !== '') {
            return $title;
        }

        foreach ($node->children() ?? [] as $child) {
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

        $def = (string) $node['def'];
        if ($def !== '') {
            return sprintf('[%s](%s)', $def, $def);
        }

        return '';
    }
}
