<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use ReflectionNamedType;
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
    private $description;

    /**
     * @var bool
     * @readonly
     */
    private $isOptional;

    /** @var string  */
    private $default;

    /** @var string  */
    private $example;

    /** @var ?SchemaConstraints */
    private $constraints = null;

    /** @var ArrayObject<string, string> */
    private $semanticDictionary;

    /**
     * @param ArrayObject<string, string> $semanticDictionary
     */
    public function __construct(
        ReflectionParameter $parameter,
        TagParam $tagParam,
        ?SchemaProp $prop,
        ArrayObject $semanticDictionary
    ) {
        $this->name = $parameter->name;
        $this->type = $this->getType($parameter);
        $this->isOptional = $parameter->isOptional();
        $this->default = $parameter->isDefaultValueAvailable() ? $this->getDefaultString($parameter) : '';
        $this->description = $tagParam->description;
        $this->example = $prop->example ?? '';
        $this->semanticDictionary = $semanticDictionary;
        if ($prop) {
            $this->setByProp($prop);

            return;
        }

        if ($tagParam->description) {
            $this->description = $tagParam->description;

            return;
        }

        $this->description = $semanticDictionary[$parameter->name] ?? '';
    }

    private function getType(ReflectionParameter $parameter): string
    {
        $namedType = $parameter->getType();
        if (! $namedType instanceof ReflectionNamedType) {
            // @codeCoverageIgnoreStart
            return '';
            // @codeCoverageIgnoreEnd
        }

        return $namedType->getName();
    }

    private function getDefaultString(ReflectionParameter $parameter): string
    {
        /** @var array<mixed>|bool|int|float|string $default */
        $default = $parameter->getDefaultValue();
        if (is_array($default)) {
            return str_replace(PHP_EOL, '', strtolower(var_export($default, true)));
        }

        return (string) $default;
    }

    private function setByProp(SchemaProp $prop): void
    {
        $this->constraints = $prop->constraints;
        if (! $this->description) {
            /** @psalm-suppress InaccessibleProperty */
            $this->description = $prop->description;
        }
    }

    /**
     * @psalm-external-mutation-free
     */
    public function __toString(): string
    {
        $required = $this->isOptional ? 'Optional' : 'Required';

        return sprintf('| %s | %s | %s | %s | %s | %s | %s ', $this->name, $this->type, $this->description, $this->default, $required, (string) $this->constraints, $this->example);
    }
}
