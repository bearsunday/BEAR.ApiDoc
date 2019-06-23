<?php

namespace BEAR\ApiDoc;

use function str_replace;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MarkdownEscapeExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('mdescape', [$this, 'mdescape'])
        ];
    }

    public function mdescape(string $string) : string
    {
        if (substr_count($string, '_') < 2) {
            return $string;
        }

        return str_replace('_', '\_', $string);
    }
}
