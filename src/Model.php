<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

/**
 * @psalm-pure
 */
final class Model
{
    /** @var string */
    public $title = '';

    /** @var string */
    public $file;

    public function __construct(string $title, string $file)
    {
        $this->title = $title;
        $this->file = $file;
    }
}
