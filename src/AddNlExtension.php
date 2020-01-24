<?php

namespace BEAR\ApiDoc;

use const PHP_EOL;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AddNlExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('add_nl', [$this, 'addNl'])
        ];
    }

    public function addNl(?string $text) : string
    {
        if ($text) {
            return (string) $text . PHP_EOL . PHP_EOL;
        }

        return '';
    }
}
