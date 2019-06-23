<?php

namespace BEAR\ApiDoc;

use function parse_url;
use Rize\UriTemplate\Parser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class RevRouteExtension extends AbstractExtension
{
    /**
     * @var iterable
     */
    private $map;

    public function __construct(iterable $map)
    {
        $this->map = $map;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('rev_route', [$this, 'revRoute'])
        ];
    }

    public function revRoute(string $uri) : string
    {
        foreach ($this->map as $path => $route) {
            if ($route->path === $uri) {
                return (string) $path;
            }
        }

        return $this->cleanupUriTemplate($uri);
    }

    private function cleanupUriTemplate(string $uri) : string
    {
        $p = (new Parser())->parse($uri);
        $token = $p[0]->getToken();

        return parse_url($token, PHP_URL_PATH) ?? $uri; // resolve app://self/path full path
    }
}
