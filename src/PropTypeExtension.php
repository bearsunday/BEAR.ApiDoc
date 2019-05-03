<?php
namespace BEAR\ApiDoc;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use function str_replace;

final class PropTypeExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('prop_type', [$this, 'propType'])
        ];
    }

    public function propType(string $type, array $prop, string $schemaFile) : string
    {
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
