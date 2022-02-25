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
use function is_object;
use function is_string;
use function json_encode;
use function property_exists;
use function sprintf;
use function ucfirst;

use const JSON_PRETTY_PRINT;

/**
 * @psalm-pure
 */
final class Schema
{
    /** @var string */
    public $title;

    /** @var array<string, SchemaProp> */
    public $props = [];

    /** @var string */
    public $type;

    /** @var SplFileInfo */
    public $file;

    /** @var array<string> */
    public $examples = [];

    /** @var array<Ref> */
    private $refs = [];

    /** @var object */
    private $schema;

    /** @var ArrayObject<string, string> */
    private $semanticDictionary;

    /**
     * @param ArrayObject<string, string> $semanticDictionary
     */
    public function __construct(SplFileInfo $file, object $schema, ArrayObject $semanticDictionary)
    {
        /** @psalm-suppress MixedAssignment */
        $this->title = $schema->title ?? ''; // @phpstan-ignore-line
        $this->file = $file;
        $this->schema = $schema;
        assert(isset($schema->type));
        assert(is_string($schema->type));
        $this->type = $schema->type;
        /** @var array<string, string> $required */
        $required = $schema->required ?? []; // @phpstan-ignore-line
        $this->semanticDictionary = $semanticDictionary;
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

| Item Type |  Constraint |
|-----------|------------|
| {$type} | {$constraint} |         
EOT;
    }

    private function getItemType(object $schema): string
    {
        if (isset($schema->type) && is_string($schema->type)) {
            return $this->returnType($schema->type);
        }

        if (isset($schema->{'$ref'})) {
            $ref = new Ref((string) $schema->{'$ref'}, $this->file, $this->schema);

            return $ref->type;
        }

        // @codeCoverageIgnoreStart
        throw new RuntimeException();
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param array<string, string> $required
     */
    private function setObject(object $schema, array $required): void
    {
        assert(isset($schema->properties));
        foreach ($schema->properties as $name => $property) {
            assert(is_string($name));
            assert(is_object($property));
            $description = property_exists($property, 'description') ? (string) $property->description : '';
            $title = property_exists($property, 'title') ? (string) $property->title : '';
            $titleDescription = $title && $description ? sprintf('%s - %s', $title, $description) : $title . $description;
            $type = $this->getType($property, $schema);
            $constraint = new SchemaConstraints($property, $this->file);
            $isOptional = ! in_array($name, $required);
            $example = property_exists($property, 'example') ? (string) $property->example : '';
            /** @psalm-suppress InaccessibleProperty */
            $this->props[$name] = new SchemaProp($name, $type, $isOptional, $this->getDescription($titleDescription, $name), $constraint, $example);
        }
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
        $propertyRef = $property->{'$ref'} ?? ''; // @phpstan-ignore-line
        assert(is_string($propertyRef));
        if ($propertyRef) {
            $ref = new Ref($propertyRef, $this->file, $schema);
            $this->refs[] = $ref;

            return $this->returnType($ref->type);
        }

        assert(isset($property->type));
        /** @var list<string>|string $type */
        $type = $property->type;

        return $this->returnType($type);
    }

    /**
     * @param string|list<string> $type
     */
    private function returnType($type): string
    {
        if (is_array($type)) {
            $type = array_map(static function (string $item): string {
                return $item === 'integer' ? 'int' : $item;
            }, $type);
            $type = implode('&#124;', $type);
        }

        return $type === 'integer' ? 'int' : $type;
    }
}
