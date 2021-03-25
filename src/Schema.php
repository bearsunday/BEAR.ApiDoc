<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use SplFileInfo;

use function assert;
use function is_object;
use function is_string;
use function json_encode;
use function sprintf;
use function ucfirst;

final class Schema
{
    /** @var string */
    private $title;

    /**
     * @var array<string, SchemaProp>
     * @readonly
     */
    public $props = [];

    /** @var string */
    public $type;

    /** @var string */
    private $name;

    /** @var SplFileInfo */
    public $file;

    /** @var array<Ref> */
    private $refs;

    /** @var object */
    private $schema;

    /** @var array */
    private $example;

    public function __construct(SplFileInfo $file, object $schema)
    {
        $this->title = $schema->title ?? '';
        $this->file = $file;
        $this->schema = $schema;
        $this->example = isset($schema->example) ? json_encode($schema->example) : '';
        assert(isset($schema->type));
        $this->type = $schema->type;
        $requierd = $schema->required ?? [];
        if ($schema->type === 'object') {
            $this->setObject($schema, $requierd);
        }
    }

    public function title()
    {
        $title = $this->title ? sprintf('%s: %s', ucfirst($this->type), $this->title) : ucfirst($this->type);

        return sprintf('[%s](schema/%s)', $title, $this->file->getFilename());
    }

    public function toStringTypeArray(): string
    {
        $type = $this->getItemType($this->schema->items);
        $constraint = (string) new SchemaConstrains($this->schema->items, $this->file);

        return <<<EOT
{$this->title()}

| Type  | Item Type |  Constrain |
|-------|-----------|------------|
| Array | {$type} | {$constraint} |         
EOT;
    }

    private function getItemType(object $schema): string
    {
        if (isset($schema->type)) {
            return $schema->type;
        }

        if (isset($schema->{'$ref'})) {
            $ref = new Ref($schema->{'$ref'}, $this->file, $this->schema);

            return $ref->type;
        }
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
            $constrain = new SchemaConstrains($property, $this->file);
            $isOptional = ! isset($requierd[$name]);
            $example = $property->example ?? '';
            $this->props[$name] = new SchemaProp($name, $type, $isOptional, $titleDescrptipon, $constrain, (string) $example);
        }
    }

    private function getType(object $property, object $schema): string
    {
        $propertyRef = $property->{'$ref'} ?? '';
        if ($propertyRef) {
            $ref = new Ref($propertyRef, $this->file, $schema);
            $this->refs[] = $ref;
            $type = $ref->type;

            return $this->returnType($type);
        }

        return $this->returnType($property->type);
    }

    private function returnType(string $type): string
    {
        return $type === 'integer' ? 'int' : $type;
    }
}
