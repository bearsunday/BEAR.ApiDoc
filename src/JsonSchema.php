<?php
namespace BEAR\ApiDoc;

use function json_decode;

final class JsonSchema
{
    public $id;
    public $docHref;
    public $constatins = [];
    public $constrainNum;

    public function __construct(string $json)
    {
        $schema = json_decode($json);
        $this->id = $this->getSchemaId($schema);
        $this->docHref = 'schema/' . $this->id;
        $this->schema = $schema;
        $this->constatins = isset($schema->properties) ? $this->getConstrains($schema->properties) : [];
        $this->constrainNum = $this->getConstrainNum($this->constatins);
    }

    private function getSchemaId($schema) : string
    {
        $path = parse_url($schema->id, PHP_URL_PATH);
        if ($path) {
            return pathinfo($schema->id, PATHINFO_BASENAME);
        }

        return $schema->id;
    }

    private function getConstrains($properties) : array
    {
        $constatins = [];
        foreach ($properties as $name => $property) {
            unset($property->type, $property->description);
            $prop = (array) $property;
            if ($prop === []) {
                $constatins[$name] = [
                    'first' => [],
                    'extra' => []
                ];

                continue;
            }
            $i = 0;
            foreach ($prop as $id => $val) {
                if ($i++ === 0) {
                    $constatins[$name] = [
                        'first' => [$id => $val],
                        'extra' => []
                    ];
                    continue;
                }
                $constatins[$name]['extra'] = [$id => $val];
            }
        }

        return $constatins;
    }

    private function getConstrainNum(array $constrains) : array
    {
        $constatinNum = [];
        foreach ($constrains as $name => $constrain) {
            $constatinNum[$name] = count($constrain['extra']) + 1;
        }

        return $constatinNum;
    }
}
