<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

/**
 * @psalm-pure
 */
final class TagParam
{
    /** @var string */
    public $type = '';

    /** @var string */
    public $description = '';

    public function __construct(string $type, string $description)
    {
        $this->type = $type;
        $this->description = $description;
    }
}
