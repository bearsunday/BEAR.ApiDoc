<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Doc;

use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Location;
use phpDocumentor\Reflection\Types\Context;
use RuntimeException;

final class ThrowingDocBlockFactory implements DocBlockFactoryInterface
{
    /** @param array<string, class-string<\phpDocumentor\Reflection\DocBlock\Tag>> $additionalTags */
    public static function createInstance(array $additionalTags = []): never
    {
        unset($additionalTags);

        throw new RuntimeException('ThrowingDocBlockFactory::createInstance is not supported.');
    }

    /** @param string|object $docblock */
    public function create($docblock, ?Context $context = null, ?Location $location = null): DocBlock
    {
        unset($docblock, $context, $location);

        throw new InvalidArgumentException('Simulated malformed docblock.');
    }
}
