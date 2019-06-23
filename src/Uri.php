<?php

namespace BEAR\ApiDoc;

final class Uri
{
    /**
     * @var array
     */
    public $allow;

    /**
     * @var array
     */
    public $doc;

    /**
     * @var string
     */
    public $uriPath;

    /**
     * @var string
     */
    public $filePath;

    public function __construct(array $allow, array $doc, string $uriPath, string $filePath)
    {
        $this->allow = $allow;
        $this->doc = $doc;
        $this->uriPath = $uriPath;
        $this->filePath = $filePath;
    }
}
