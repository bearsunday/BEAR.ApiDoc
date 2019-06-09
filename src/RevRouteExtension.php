<?php
namespace BEAR\ApiDoc;

use Rize\UriTemplate\Parser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use function parse_url;

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
        $uri = $this->cleanupUriTemplate($uri);

        return $uri;
    }

    private function cleanupUriTemplate(string $uri) : string
    {
        $p = (new Parser())->parse($uri);
        $token = $p[0]->getToken();
        $uri = parse_url($token, PHP_URL_PATH) ?? $uri; // resolve app://self/path full path

        return $uri;
    }
}
