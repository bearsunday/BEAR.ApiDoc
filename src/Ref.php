<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use LogicException;
use SplFileInfo;

use function assert;
use function explode;
use function file_exists;
use function file_get_contents;
use function filter_var;
use function json_decode;
use function sprintf;
use function substr;

use const FILTER_VALIDATE_URL;

final class Ref
{
    /** @var string */
    public $title;

    /**
     * @var string
     * @readonly
     */
    public $type = '';

    /** @var object */
    public $json;

    /** @var string */
    public $href;

    /** @var object */
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
        $path = '';
        foreach ($paths as $path) {
            $target = $target->{$path};
        }

        assert(isset($target->type));
        $this->json = $target;
        /** @psalm-suppress InaccessibleProperty */
        $this->type = $target->type;
        $this->title = $target->title ??  $path;
    }

    private function getExternalRef(string $ref, SplFileInfo $file): void
    {
        $filePath = $this->getFilePath($ref, $file);
        $this->json = $schema = json_decode((string) file_get_contents($filePath));
        /** @psalm-suppress InaccessibleProperty */
        $this->type = $schema->type ?? '';
        $this->title = $schema->title ?? '';
    }

    private function getFilePath(string $ref, SplFileInfo $file): string
    {
        if (filter_var($ref, FILTER_VALIDATE_URL)) {
            return $ref;
        }

        $refFile = sprintf('%s/%s', $file->getPath(), $ref);
        if (! file_exists($refFile)) {
            throw new LogicException('Invalid $ref' . $ref);
        }

        return $refFile;
    }
}
