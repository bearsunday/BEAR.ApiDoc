<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Rize\UriTemplate;
use Rize\UriTemplate\Node\Literal;

use function assert;
use function parse_url;
use function sprintf;
use function substr;

final class Src
{
    /** @var string */
    private $src;

    public function __construct(string $src)
    {
        $this->src = $src;
    }

    public function __toString()
    {
        $uriTemplate = new UriTemplate($this->src);
        $parser = $uriTemplate->getParser();
        $urls = $parser->parse($this->src);
        $literal = $urls[0];
        assert($literal instanceof Literal);
        $token = $literal->getToken();
        $parseUrl = parse_url(substr($token, 1));
        assert(isset($parseUrl['path']));
        $resourcePath = $parseUrl['path'];

        return sprintf('[%s](%s.md)', $this->src, $resourcePath);
    }
}
