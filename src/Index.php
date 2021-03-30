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

    /** @var array<string, string> */
    private $objects;

    /**
     * @param array<string, string> $paths
     * @param array<string, string> $objects
     */
    public function __construct(string $title, string $description, array $paths, array $objects)
    {
        $this->title = $title;
        $this->description = $description;
        $this->paths = $paths;
        $this->objects = $objects;
    }

    public function __toString(): string
    {
        $paths = $objects = '';
        foreach ($this->paths as $route => $path) {
            $paths .= sprintf(' * [%s](%s.md) ', $route, $path) . PHP_EOL;
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
