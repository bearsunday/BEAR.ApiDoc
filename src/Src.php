<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Rize\UriTemplate;
use Rize\UriTemplate\Node\Literal;

use function assert;
use function ltrim;
use function parse_url;
use function preg_replace;
use function sprintf;
use function strtolower;
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
        $resourcePath = substr($parseUrl['path'], 1);

        return sprintf('[<code>%s</code>](%s.md)', $this->src, $this->camel2kebab($resourcePath));
    }

    private function camel2kebab(string $str): string
    {
        return ltrim(strtolower((string) preg_replace('/[A-Z]/', '-\0', $str)), '-');
    }
}
