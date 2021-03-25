<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

final class Index
{
    /** @var array<string, string> */
    private $paths;

    /** @var array<string, string> */
    private $objects;

    public function __construct(array $paths, array $objects)
    {
    }
}
