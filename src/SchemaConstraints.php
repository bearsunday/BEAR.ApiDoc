<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use SplFileInfo;

use function assert;
use function file_exists;
use function filter_var;
use function in_array;
use function is_string;
use function json_encode;
use function sprintf;

use const FILTER_VALIDATE_URL;
use const JSON_UNESCAPED_SLASHES;

/**
 * @psalm-pure
 */
final class SchemaConstraints
{
    /**
     * @var array<string, mixed>
     * @readonly
     */
    public $constrains;

    public function __construct(object $property, SplFileInfo $file)
    {
        $constrains = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($property as $name => $value) { // @phpstan-ignore-line
            if (in_array($name, ['type', 'title', 'description', 'example', 'examples'])) {
                continue;
            }

            if ($name === '$ref') {
                assert(is_string($value));
                $constrains['$ref'] = $this->getRefLink($value, $file);
                continue;
            }

            $constrains[(string) $name] = $value;
        }

        $this->constrains = $constrains;
    }

    private function getRefLink(string $value, SplFileInfo $file): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return sprintf('[%s](%s)', $value, $value);
        }

        $refFile = sprintf('%s/%s', $file->getPath(), $value);
        if (file_exists($refFile)) {
            return sprintf('[%s](../schema/%s)', $value, $value);
        }

        return $value;
    }

    public function __toString(): string
    {
        return $this->constrains === [] ? '' : (string) json_encode($this->constrains, JSON_UNESCAPED_SLASHES);
    }
}
