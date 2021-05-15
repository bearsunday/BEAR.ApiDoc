<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use phpDocumentor\Reflection\DocBlock\Tags\Link;
use phpDocumentor\Reflection\DocBlockFactory;

use const PHP_EOL;

/**
 * @psalm-pure
 */
class PhpDoc
{
    /**
     * @return array{0: string, 1:string, 2:string}
     */
    public function __invoke(string $docComment): array
    {
        if (! $docComment) {
            return ['', '', ''];
        }

        $factory = DocBlockFactory::createInstance();
        $docblock = $factory->create($docComment);
        $summary = $docblock->getSummary() . PHP_EOL . PHP_EOL;
        $description = (string) $docblock->getDescription() . PHP_EOL . PHP_EOL;
        /** @var list<Link> $tagLinks */
        $tagLinks = $docblock->getTagsByName('link');
        $links = (string) new TagLinks($tagLinks) . PHP_EOL . PHP_EOL;

        return [$summary, $description, $links];
    }
}
