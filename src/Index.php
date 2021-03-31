<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function sprintf;

use const PHP_EOL;

final class Index
{
    /** @var string  */
    private $title;

    /** @var string */
    private $description;

    /** @var array<string, string> */
    private $paths;

    /** @var list<string> */
    private $objects;

    /** @var string */
    private $ext;

    /**
     * @param array<string, string> $paths
     * @param list<string> $objects
     */
    public function __construct(string $title, string $description, array $paths, array $objects, string $ext)
    {
        $this->title = $title;
        $this->description = $description;
        $this->paths = $paths;
        $this->objects = $objects;
        $this->ext = $ext;
    }

    public function __toString(): string
    {
        $paths = $objects = '';
        foreach ($this->paths as $route => $path) {
            $paths .= sprintf(' * [%s](%s.%s) ', $route, $path, $this->ext) . PHP_EOL;
        }

        foreach ($this->objects as $objectName => $objectFile) {
            $objects .= sprintf(' * [%s](schema/%s) ', $objectName, $objectFile) . PHP_EOL;
        }

        return <<<EOT
# API Doc
 * {$this->title}

{$this->description}

## Paths
{$paths}

## Objects
{$objects}
EOT;
    }
}
