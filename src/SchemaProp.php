<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function sprintf;

final class SchemaProp
{
    /**
     * @var SchemaConstrains
     * @readonly
     */
    public $constrains;

    /**
     * @var string
     * @readonly
     */
    public $descripton;

    /** @var string */
    public $example;

    /** @var string */
    private $name;

    /** @var string */
    private $type;

    /** @var bool */
    private $isOptional;

    public function __construct(string $name, string $type, bool $isOptional, string $description, SchemaConstrains $constrains, string $example)
    {
        $this->name = $name;
        $this->type = $type;
        $this->isOptional = $isOptional;
        $this->descripton = $description;
        $this->constrains = $constrains;
        $this->example = $example;
    }

    public function __toString()
    {
        $requred = $this->isOptional ? 'Optional' : 'Required';

        return sprintf('| %s | %s | %s | %s | %s | %s |', $this->name, $this->type, $this->descripton, $requred, (string) $this->constrains, $this->example);
    }
}
