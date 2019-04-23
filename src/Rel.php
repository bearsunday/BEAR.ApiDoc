<?php
namespace BEAR\ApiDoc;

final class Rel
{
    /**
     * @var array
     */
    public $rel;

    /**
     * @var array
     */
    public $href;

    /**
     * @var string
     */
    public $method;

    /**
     * @var string
     */
    public $title;

    public function __construct(array $rel, array $href, string $method, string $title)
    {
        $this->rel = $rel;
        $this->href = $href;
        $this->method = $method;
        $this->title = $title;
    }
}
