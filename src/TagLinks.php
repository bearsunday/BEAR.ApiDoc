<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use phpDocumentor\Reflection\DocBlock\Tags\Link;
use Stringable;

use function implode;
use function sprintf;

use const PHP_EOL;

/**
 * @psalm-pure
 */
final class TagLinks implements Stringable
{
    /**
     * @param array<Link> $links
     */
    public function __construct(
        private readonly array $links
    ) {
    }

    #[\Override]
    public function __toString(): string
    {
        $view = [];
        foreach ($this->links as $link) {
            $view[] = sprintf(' * %s [%s](%s)', (string) $link->getDescription(), $link->getLink(), $link->getLink());
        }

        return implode(PHP_EOL, $view);
    }
}
