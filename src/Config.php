<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use SimpleXMLElement;

use function property_exists;

class Config
{
    /** @var string */
    public $appName;

    /** @var string */
    public $scheme;

    /** @var string */
    public $docDir;

    /** @var string */
    public $format;

    /** @var string */
    public $title;

    /** @var string */
    public $description;

    /** @var array<SimpleXMLElement> */
    public $links;

    /** @var string */
    public $alps;

    /**
     * @psalm-suppress
     */
    public function __construct(SimpleXMLElement $xml)
    {
        $this->appName = property_exists($xml, 'appName') ? (string) $xml->appName : '';
        $this->title = property_exists($xml, 'title') ? (string) $xml->title : '';
        $this->description = property_exists($xml, 'description') ? (string) $xml->description : '';
        $this->scheme = property_exists($xml, 'scheme') ? (string) $xml->scheme : '';
        $this->docDir = property_exists($xml, 'docDir') ? (string) $xml->docDir : '';
        $this->format = property_exists($xml, 'format') ? (string) $xml->format : '';
        $this->title = property_exists($xml, 'title') ? (string) $xml->title : '';
        $this->alps = property_exists($xml, 'alps') ? (string) $xml->alps : '';
        /** @var array<SimpleXMLElement> $links */
        $links = property_exists($xml, 'links') ? $xml->links : [];
        $this->links = $links;
    }
}
