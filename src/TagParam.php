<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

final class TagParam
{
    /**
     * @var string
     * @readonly
     */
    public $type = '';

    /**
     * @var string
     * @readonly
     */
    public $description = '';

    public function __construct(string $type, string $description)
    {
        $this->type = $type;
        $this->description = $description;
    }
}
