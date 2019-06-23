<?php

namespace BEAR\ApiDoc;

use function implode;
use function is_array;
use function json_encode;
use function sprintf;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ConstrainExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('constrain', [$this, 'constrain'])
        ];
    }

    public function constrain(array $prop) : string
    {
        $consrains = [];
        foreach ($prop as $key => $item) {
            if ($key[0] === '$' || $key === 'type' || $key === 'items' || $key === 'description') {
                continue;
            }
            $consrainVal = is_array($item) ? json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $item;
            $consrains[] = sprintf('%s:%s', $key, $consrainVal);
        }

        return implode(', ', $consrains);
    }
}
