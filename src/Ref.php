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
    /**
     * @var string
     * @readonly
     */
    public $title;

    /**
     * @var string
     * @readonly
     */
    public $type = '';

    /**
     * @var object
     * @readonly
     */
    public $json;

    /**
     * @var string
     * @readonly
     */
    public $href;

    /**
     * @var object
     * @readonly
     */
    public $schema;

    public function __construct(string $ref, SplFileInfo $file, object $schema)
    {
        $this->fileName = $file->getFilename();
        $isInlineRef = $ref[0] === '#';
        $isInlineRef ? $this->getInlineRef($ref, $file, $schema) :  $this->getExternalRef($ref, $file);
    }

    private function getInlineRef(string $ref, SplFileInfo $file, object $schema): void
    {
        $target = $schema;
        $paths = explode('/', substr($ref, 2));
        foreach ($paths as $path) {
            $target = $target->{$path};
        }

        assert(isset($target->type));
        $this->json = $target;
        $this->type = $target->type;
        $this->href = $path;
        $this->title = $target->title ?? $path;
    }

    private function getExternalRef(string $ref, SplFileInfo $file): void
    {
        [$filePath, $href] = $this->getFilePath($ref, $file);
        $this->json = $schema = json_decode((string) file_get_contents($filePath));
        $this->type = $schema->type ?? '';
        $this->href = sprintf('schema/%s', $ref);
        $this->title = $schema->title ?? '';
    }

    /**
     * @return array|string[]
     */
    private function getFilePath(string $ref, SplFileInfo $file): array
    {
        if (filter_var($ref, FILTER_VALIDATE_URL)) {
            return [$ref, $ref];
        }

        $refFile = sprintf('%s/%s', $file->getPath(), $ref);
        if (! file_exists($refFile)) {
            throw new LogicException('Invalid $ref' . $ref);
        }

        return [$refFile, $ref];
    }
}
