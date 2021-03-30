<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use ReflectionParameter;

use function is_array;
use function sprintf;
use function str_replace;
use function strtolower;
use function var_export;

use const PHP_EOL;

final class DocParam
{
    /**
     * @var string
     * @readonly
     */
    private $name;

    /**
     * @var string
     * @readonly
     */
    private $type;

    /**
     * @var string
     * @readonly
     */
    private $descripton;

    /**
     * @var bool
     * @readonly
     */
    private $isOptional;

    /** @var string  */
    private $default = '';

    /** @var string  */
    private $example = '';

    /** @var SchemaConstraints */
    private $constaints;

    /** @var ArrayObject */
    private $semanticDictionary;

    public function __construct(
        ReflectionParameter $parameter,
        TagParam $tagParam,
        ?SchemaProp $prop,
        ArrayObject $semanticDictionary
    ) {
        $this->name = $parameter->name;
        $this->type = $parameter->getType()->getName() ?: $tagParam->type;
        $this->isOptional = $parameter->isOptional();
        $this->default = $parameter->isDefaultValueAvailable() ? $this->getDefaultString($parameter) : '';
        $this->descripton = $tagParam->description;
        $this->example = $prop->example ?? '';
        if ($prop) {
            $this->setByProp($prop);
        }

        $this->semanticDictionary = $semanticDictionary;
    }

    private function getDefaultString(ReflectionParameter $parameter): string
    {
        $default = $parameter->getDefaultValue();
        if (is_array($default)) {
            return str_replace(PHP_EOL, '', strtolower(var_export($default, true)));
        }

        $stringDefault = (string) $default;
        if ($stringDefault) {
            return $stringDefault;
        }

        return $this->semanticDictionary[$parameter->name] ?? '';
    }

    private function setByProp(SchemaProp $prop): void
    {
        $this->constaints = $prop->constraints;
        if (! $this->descripton) {
            /** @psalm-suppress InaccessibleProperty */
            $this->descripton = $prop->descripton;
        }
    }

    public function __toString()
    {
        $requred = $this->isOptional ? 'Optional' : 'Required';

        return sprintf('| %s | %s | %s | %s | %s | %s |', $this->name, $this->type, $this->descripton, $this->default, $this->constaints, $this->example);
    }
}
