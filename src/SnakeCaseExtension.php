<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SnakeCaseExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('snakecase', [$this, 'snakecase'])
        ];
    }

    public function snakecase(string $string) : string
    {
        return ltrim(strtolower((string) preg_replace('/[A-Z]/', '_\0', $string)), '_');
    }
}
