<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Rize\UriTemplate;
use Rize\UriTemplate\Node\Literal;
use Stringable;

use function assert;
use function is_array;
use function ltrim;
use function parse_url;
use function preg_replace;
use function sprintf;
use function strtolower;
use function substr;

/** @psalm-pure */

final class Src implements Stringable
{
    public function __construct(
        private readonly string $src,
        private readonly string $ext
    ) {
    }

    #[\Override]
    public function __toString(): string
    {
        $uriTemplate = new UriTemplate($this->src);
        $parser = $uriTemplate->getParser();
        $urls = $parser->parse($this->src);
        $literal = $urls[0];
        assert($literal instanceof Literal);
        $token = $literal->getToken();
        $parseUrl = parse_url(substr($token, 1));
        assert(is_array($parseUrl));
        assert(isset($parseUrl['path']));
        $resourcePath = $parseUrl['path'];

        return sprintf('[<code>%s</code>](%s.%s)', $this->src, $this->camel2kebab($resourcePath), $this->ext);
    }

    private function camel2kebab(string $str): string
    {
        return ltrim(strtolower((string) preg_replace('/[A-Z]/', '-\0', $str)), '-');
    }
}
