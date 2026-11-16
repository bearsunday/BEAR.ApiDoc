<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use RuntimeException;
use SplFileInfo;

use function assert;
use function basename;
use function dirname;
use function explode;
use function file_exists;
use function filter_var;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function property_exists;
use function realpath;
use function sprintf;
use function substr;

use const FILTER_VALIDATE_URL;

final class Ref
{
    /** Keywords shown in place of a left-out `type`, first match wins */
    public const TYPE_KEYWORDS = ['anyOf', 'oneOf', 'allOf', 'enum', 'const'];

    public string $title = '';

    public string $type = '';

    public ?object $json = null;

    public string $href = '';

    public ?object $schema = null;

    public function __construct(string $ref, SplFileInfo $file, object $schema)
    {
        $isInlineRef = $ref[0] === '#';
        $isInlineRef ? $this->getInlineRef($ref, $schema) :  $this->getExternalRef($ref, $file);
    }

    private function getInlineRef(string $ref, object $schema): void
    {
        $target = $schema;
        $paths = explode('/', substr($ref, 2));
        foreach ($paths as $path) {
            if (! is_object($target) || ! property_exists($target, $path)) {
                throw new RuntimeException('Invalid $ref ' . $ref);
            }

            /** @psalm-suppress MixedAssignment */
            $target = $target->{$path};
        }

        if (! is_object($target)) {
            throw new RuntimeException('Invalid $ref ' . $ref);
        }

        $this->json = $target;
        $this->type = self::typeOf($target);
        $title = $target->title ?? $path;
        assert(is_string($title));
        $this->title = $title;
    }

    private function getExternalRef(string $ref, SplFileInfo $file): void
    {
        $filePath = $this->getFilePath($ref, $file);
        $schema = (new JsonFile())->object($filePath);
        $this->json = $schema;
        $this->type = self::typeOf($schema);
        $this->title = isset($schema->title) && is_string($schema->title) ? $schema->title : '';
    }

    /** The written `type` joined with `|`, else the first keyword of TYPE_KEYWORDS used, else '' */
    public static function typeOf(object $schema): string
    {
        if (isset($schema->type)) {
            /** @var list<string>|string $type */
            $type = $schema->type;

            return is_array($type) ? implode('|', $type) : $type;
        }

        foreach (self::TYPE_KEYWORDS as $keyword) {
            if (property_exists($schema, $keyword)) {
                return $keyword;
            }
        }

        return '';
    }

    private function getFilePath(string $ref, SplFileInfo $file): string
    {
        if (filter_var($ref, FILTER_VALIDATE_URL)) {
            return $ref; // @codeCoverageIgnore
        }

        $refFile = sprintf('%s/%s', $file->getPath(), $ref);
        if (! file_exists($refFile)) {
            throw new RuntimeException('Invalid $ref' . $ref);
        }

        return $refFile;
    }

    /** File name of the schema that the external `$ref` of `$property` points to, when it sits next to `$file` */
    public static function siblingSchemaFile(mixed $property, SplFileInfo $file): ?string
    {
        $ref = is_object($property) ? $property->{'$ref'} ?? null : null;
        if (! is_string($ref) || $ref === '' || $ref[0] === '#') {
            return null;
        }

        $target = realpath(sprintf('%s/%s', $file->getPath(), $ref));

        return $target !== false && dirname($target) === realpath($file->getPath()) ? basename($target) : null;
    }
}
