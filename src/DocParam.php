<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Composer\Semver\Constraint\Constraint;
use ReflectionParameter;

use function sprintf;

final class DocParam
{
    /**
     * @var string
     * @readonly
     */
    public $name;

    /**
     * @var string
     * @readonly
     */
    public $type;

    /**
     * @var string
     * @readonly
     */
    public $descripton;

    /**
     * @var bool
     * @readonly
     */
    public $isOptional;

    /** @var string  */
    private $default = '';

    /** @var string  */
    private $example = '';

    /** @var Constraint */
    public $constains;

    public function __construct(
        ReflectionParameter $parameter,
        TagParam $tagParam,
        ?SchemaProp $prop
    ) {
        $this->name = $parameter->name;
        $this->type = (string) $parameter->getType() ?: $tagParam->type;
        $this->isOptional = $parameter->isOptional();
        $this->default = $parameter->isDefaultValueAvailable() ? (string) $parameter->getDefaultValue() : '';
        $this->descripton = $tagParam->description;
        $this->example = $prop->example ?? '';
        if ($prop) {
            $this->setByProp($prop);
        }
    }

    private function setByProp(SchemaProp $prop): void
    {
        $this->constains = $prop->constraints;
        if (! $this->descripton) {
            $this->descripton = $prop->description;
        }
    }

    public function __toString(): string
    {
        $requred = $this->isOptional ? 'Optional' : 'Required';

        return sprintf('| %s | %s | %s | %s | %s |', $this->name, $this->type, $this->descripton, $this->default, $this->example);
    }
}
