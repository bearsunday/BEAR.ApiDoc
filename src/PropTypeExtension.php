<?php

namespace BEAR\ApiDoc;

use function str_replace;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class PropTypeExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('prop_type', [$this, 'propType'])
        ];
    }

    public function propType($type, array $prop, string $schemaFile) : string
    {
        if (is_array($type)) {
            $type = implode('|', $type);
        }
        $linkType = $this->link($prop, $type, $schemaFile);
        if ($type === 'array') {
            $linkType .= '[]';
        }

        return $linkType;
    }

    private function link(array $prop, string $type, string $schemaFile) : string
    {
        $propItemKey = isset($prop['items']) ? key($prop['items']) : '';
        if ($propItemKey === '$ref') {
            return $this->ref($prop['items'], $schemaFile);
        }
        if (isset($prop['$ref'])) {
            return $this->ref($prop, $schemaFile);
        }

        return $type;
    }

    private function ref(array $items, string $schemaFile) : string
    {
        $ref = current($items);
        if (is_int(strpos($ref, 'json'))) {
            $jsonFile = str_replace('./', '', $ref);

            return sprintf('$ref [%s](../schema/%s)', $jsonFile, $jsonFile);
        }

        return sprintf('$ref [#](../%s)', $schemaFile);
    }
}
