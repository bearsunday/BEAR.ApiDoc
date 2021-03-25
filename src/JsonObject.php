<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

final class JsonObject
{
    /** @var string */
    private $title;

    /** @var string */
    private $path;

    public function __construct(object $object)
    {
        $this->title = $object->title ?? '';
    }
}
