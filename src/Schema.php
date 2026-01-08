<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use RuntimeException;
use SplFileInfo;

use function array_map;
use function assert;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_iterable;
use function is_object;
use function is_string;
use function json_encode;
use function property_exists;
use function sprintf;
use function ucfirst;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/** @psalm-pure */
final class Schema
{
    /** @var string */
    public $title;

    /** @var array<string, SchemaProp> */
    public $props = [];

    /** @var string */
    public $type;

    /** @var array<string> */
    public $examples = [];

    /** @var object */
    private $schema;

    /** @param ArrayObject<string, string> $semanticDictionary */
    public function __construct(
        public SplFileInfo $file,
        object $schema,
        private ArrayObject $semanticDictionary
    ) {
        $this->title = isset($schema->title) && is_string($schema->title) ? $schema->title : '';
        $this->schema = $schema;
        assert(isset($schema->type));
        assert(is_string($schema->type));
        $this->type = $schema->type;
        /** @var array<string, string> $required */
        $required = $schema->required ?? [];
        if ($schema->type === 'object') {
            $this->setObject($schema, $required);
        }

        if (property_exists($schema, 'examples') && is_array($schema->examples)) {
            foreach ($schema->examples as $example) {
                assert(is_object($example));
                $this->examples[] = (string) json_encode($example, JSON_PRETTY_PRINT);
            }
        }
    }

    public function title(): string
    {
        $title = $this->title ? sprintf('%s: %s', ucfirst($this->type), $this->title) : ucfirst($this->type);

        return sprintf('[%s](../schema/%s)', $title, $this->file->getFilename());
    }

    public function toStringTypeArray(): string
    {
        assert(isset($this->schema->items) && is_object($this->schema->items));
        $type = $this->getItemType($this->schema->items);
        $constraint = (string) new SchemaConstraints($this->schema->items, $this->file);

        return <<<EOT
{$this->title()}

| Item Type | Constraints |
|-----------|-------------|
| {$type} | {$constraint} |
EOT;
    }

    private function getItemType(object $schema): string
    {
        if (isset($schema->type) && is_string($schema->type)) {
            return $this->returnType($schema->type);
        }

        if (isset($schema->{'$ref'}) && is_string($schema->{'$ref'})) {
            $ref = new Ref($schema->{'$ref'}, $this->file, $this->schema);

            return $ref->type;
        }

        // @codeCoverageIgnoreStart
        throw new RuntimeException();
        // @codeCoverageIgnoreEnd
    }

    /** @param array<string, string> $required */
    private function setObject(object $schema, array $required): void
    {
        if (! isset($schema->properties) || ! is_iterable($schema->properties)) {
            return;
        }

        foreach ($schema->properties as $name => $property) {
            assert(is_string($name));
            assert(is_object($property));
            $this->addProperty($name, $property, $schema, $required);
        }
    }

    /** @param array<string, string> $required */
    private function addProperty(string $name, object $property, object $schema, array $required): void
    {
        $description = property_exists($property, 'description') && is_string($property->description) ? $property->description : '';
        $title = property_exists($property, 'title') && is_string($property->title) ? $property->title : '';
        $titleDescription = $title && $description ? sprintf('%s - %s', $title, $description) : $title . $description;
        $type = $this->getType($property, $schema);
        $constraint = new SchemaConstraints($property, $this->file);
        $isOptional = ! in_array($name, $required);
        $example = $this->extractExample($property);

        /** @psalm-suppress InaccessibleProperty */
        $this->props[$name] = new SchemaProp($name, $type, $isOptional, $this->getDescription($titleDescription, $name), $constraint, $example);
    }

    private function extractExample(object $property): string
    {
        if (! property_exists($property, 'example')) {
            return '';
        }

        /** @psalm-suppress MixedAssignment */
        $exampleValue = $property->example;

        if (is_array($exampleValue) || is_object($exampleValue)) {
            return json_encode($exampleValue, JSON_THROW_ON_ERROR);
        }

        if (is_bool($exampleValue)) {
            return $exampleValue ? 'true' : 'false';
        }

        if (is_string($exampleValue) || is_int($exampleValue) || is_float($exampleValue)) {
            return (string) $exampleValue;
        }

        return '';
    }

    private function getDescription(string $titleDescription, string $id): string
    {
        if ($titleDescription) {
            return $titleDescription;
        }

        return $this->semanticDictionary[$id] ?? '';
    }

    private function getType(object $property, object $schema): string
    {
        $propertyRef = $property->{'$ref'} ?? '';
        assert(is_string($propertyRef));
        if ($propertyRef) {
            $ref = new Ref($propertyRef, $this->file, $schema);

            return $this->returnType($ref->type);
        }

        assert(isset($property->type));
        /** @var list<string>|string $type */
        $type = $property->type;

        return $this->returnType($type);
    }

    /** @param string|list<string> $type */
    private function returnType($type): string
    {
        if (is_array($type)) {
            $type = array_map(static fn (string $item): string => $item === 'integer' ? 'int' : $item, $type);
            $type = implode('|', $type);
        }

        return $type === 'integer' ? 'int' : $type;
    }
}
