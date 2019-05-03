<?php
namespace BEAR\ApiDoc;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use function preg_replace;
use function strpos;

final class RefLinkExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('reflink', [$this, 'link'])
        ];
    }

    public function link(string $json) : string
    {
        if (! strpos($json, 'definitions')) {
            return $json;
        }
        $linked = preg_replace('/#\/definitions\/(\w+)/', '<a href="#definitions/${1}">${0}</a>', $json);

        return (string) $linked;
    }
}
