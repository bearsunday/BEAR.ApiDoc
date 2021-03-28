<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use RuntimeException;
use SplFileInfo;

use function array_map;
use function assert;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function ucfirst;

final class Schema
{
    /** @var string */
    public $title;

    /**
     * @var array<string, SchemaProp>
     * @readonly
     */
    public $props = [];

    /** @var string */
    public $type;

    /** @var SplFileInfo */
    public $file;

    /** @var array<Ref> */
    private $refs;

    /** @var object */
    private $schema;

    public function __construct(SplFileInfo $file, object $schema)
    {
        $this->title = $schema->title ?? '';
        $this->file = $file;
        $this->schema = $schema;
        assert(isset($schema->type));
        $this->type = $schema->type;
        $requierd = $schema->required ?? [];
        if ($schema->type === 'object') {
            $this->setObject($schema, $requierd);
        }
    }

    public function accept(VisitorInterface $visitor): void
    {
        $visitor->visitSchema($this);
    }

    public function title(): string
    {
        $title = $this->title ? sprintf('%s: %s', ucfirst($this->type), $this->title) : ucfirst($this->type);

        return sprintf('[%s](schema/%s)', $title, $this->file->getFilename());
    }

    public function toStringTypeArray(): string
    {
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
        if (isset($schema->type)) {
            return $this->returnType($schema->type);
        }

        if (isset($schema->{'$ref'})) {
            $ref = new Ref($schema->{'$ref'}, $this->file, $this->schema);

            return $ref->type;
        }

        throw new RuntimeException();
    }

    /**
     * @param array<string> $requierd
     */
    private function setObject(object $schema, array $requierd): void
    {
        foreach ($schema->properties as $name => $property) {
            assert(is_string($name));
            assert(is_object($property));
            /** @var string */
            $title = $property->title ?? ''; // @phpstan-ignore-line
            $description = $property->description ?? ''; // @phpstan-ignore-line
            $titleDescrptipon = $title && $description ? sprintf('%s - %s', $title, $description) : $title . $description;
            /** @var string */
            $type = $this->getType($property, $schema);
            $constraint = new SchemaConstraints($property, $this->file);
            $isOptional = ! isset($requierd[$name]);
            $example = $property->example ?? '';
            /** @psalm-suppress InaccessibleProperty */
            $this->props[$name] = new SchemaProp($name, $type, $isOptional, $titleDescrptipon, $constraint, (string) $example);
        }
    }

    private function getType(object $property, object $schema): string
    {
        $propertyRef = $property->{'$ref'} ?? '';
        if ($propertyRef) {
            $ref = new Ref($propertyRef, $this->file, $schema);
            $this->refs[] = $ref;

            return $this->returnType($ref->type);
        }

        return $this->returnType($property->type);
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
