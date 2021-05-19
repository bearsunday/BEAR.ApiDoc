<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function sprintf;

/**
 * @psalm-pure
 */
final class SchemaProp
{
    /** @var SchemaConstraints */
    public $constraints;

    /** @var string */
    public $description;

    /** @var string */
    public $example;

    /** @var string */
    private $name;

    /** @var string */
    private $type;

    /** @var bool */
    private $isOptional;

    public function __construct(string $name, string $type, bool $isOptional, string $description, SchemaConstraints $constrains, string $example)
    {
        $this->name = $name;
        $this->type = $type;
        $this->isOptional = $isOptional;
        $this->description = $description;
        $this->constraints = $constrains;
        $this->example = $example;
    }

    public function __toString(): string
    {
        if ($this->name === '_links' || $this->name === '_embedded') {
            return '';
        }

        $required = $this->isOptional ? 'Optional' : 'Required';

        return sprintf('| %s | %s | %s | %s | %s | %s |', $this->name, $this->type, $this->description, $required, (string) $this->constraints, $this->example);
    }
}
