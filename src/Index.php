<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\DocBlock\Tags\Link;
use SimpleXMLElement;
use Stringable;

use function assert;
use function sprintf;

use const PHP_EOL;

final class Index implements Stringable
{
    /** @var string  */
    private $title;

    /** @var string */
    private $description;

    /** @var TagLinks */
    private $links;

    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        Config $config,
        private readonly array $paths,
        private readonly ModelRepository $objects,
        private readonly string $ext
    ) {
        $this->title = $config->title;
        $this->description = $config->description ? $config->description . PHP_EOL . PHP_EOL : '';
        $links = [];
        /** @psalm-suppress all */
        $configLink = $config->links->link ?? []; // @phpstan-ignore-line
        foreach ($configLink as $link) {
            assert($link instanceof SimpleXMLElement);
            $links[] = new Link((string) $link['href'], new Description((string) $link['rel']));
        }

        $this->links = new TagLinks($links);
    }

    public function __toString(): string
    {
        $paths = $objects = '';
        foreach ($this->paths as $route => $path) {
            $paths .= sprintf(' * [%s](paths/%s.%s) ', $route, $path, $this->ext) . PHP_EOL;
        }

        foreach ($this->objects as $objectName => $objectFile) {
            $objects .= sprintf(' * [%s](schema/%s) ', $objectName, $objectFile) . PHP_EOL;
        }

        return <<<EOT
# {$this->title}
{$this->description}{$this->links}

## Paths
{$paths}

## Objects
{$objects}
EOT;
    }
}
