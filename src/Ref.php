<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use RuntimeException;
use SplFileInfo;

use function assert;
use function explode;
use function file_exists;
use function file_get_contents;
use function filter_var;
use function is_object;
use function is_string;
use function json_decode;
use function sprintf;
use function substr;

use const FILTER_VALIDATE_URL;

final class Ref
{
    /** @var string */
    public $title = '';

    /** @var string */
    public $type = '';

    /** @var ?object */
    public $json;

    /**
     * @var string
     * @readonly
     */
    public $href = '';

    /**
     * @var ?object
     * @readonly
     */
    public $schema;

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
            /** @psalm-suppress MixedAssignment */
            $target = $target->{$path};
            assert(is_object($target));
        }

        assert(isset($target->type));
        assert(is_string($target->type));
        $this->json = $target;
        /** @psalm-suppress InaccessibleProperty */
        $this->type = $target->type;
        $title = $target->title ?? $path; // @phpstan-ignore-line
        assert(is_string($title));
        $this->title = $title;
    }

    private function getExternalRef(string $ref, SplFileInfo $file): void
    {
        $filePath = $this->getFilePath($ref, $file);
        $this->json = $schema = (object) json_decode((string) file_get_contents($filePath));
        /** @psalm-suppress MixedAssignment */
        $this->type = $schema->type ?? '';
        /** @psalm-suppress MixedAssignment */
        $this->title = $schema->title ?? '';
    }

    private function getFilePath(string $ref, SplFileInfo $file): string
    {
        if (filter_var($ref, FILTER_VALIDATE_URL)) {
            return $ref;
        }

        $refFile = sprintf('%s/%s', $file->getPath(), $ref);
        if (! file_exists($refFile)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('Invalid $ref' . $ref);
            // @codeCoverageIgnoreEnd
        }

        return $refFile;
    }
}
