<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use RuntimeException;
use SplFileInfo;

use function assert;
use function explode;
use function file_exists;
use function filter_var;
use function is_object;
use function is_string;
use function sprintf;
use function substr;

use const FILTER_VALIDATE_URL;

final class Ref
{
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
            /** @psalm-suppress MixedAssignment */
            $target = $target->{$path};
            assert(is_object($target));
        }

        assert(isset($target->type));
        assert(is_string($target->type));
        $this->json = $target;
        $this->type = $target->type;
        $title = $target->title ?? $path;
        assert(is_string($title));
        $this->title = $title;
    }

    private function getExternalRef(string $ref, SplFileInfo $file): void
    {
        $filePath = $this->getFilePath($ref, $file);
        $schema = (new JsonFile())->object($filePath);
        $this->json = $schema;
        $this->type = isset($schema->type) && is_string($schema->type) ? $schema->type : '';
        $this->title = isset($schema->title) && is_string($schema->title) ? $schema->title : '';
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
}
