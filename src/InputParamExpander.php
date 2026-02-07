<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Ray\InputQuery\Attribute\Input;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use function class_exists;

final class InputParamExpander
{
    /** @return list<ReflectionParameter> */
    public function __invoke(ReflectionMethod $method): array
    {
        $result = [];
        foreach ($method->getParameters() as $parameter) {
            $inputAttributes = $parameter->getAttributes(Input::class, ReflectionAttribute::IS_INSTANCEOF);
            if (! $inputAttributes) {
                $result[] = $parameter;
                continue;
            }

            $constructorParams = $this->expandInputParameter($parameter);
            if ($constructorParams === null) {
                $result[] = $parameter;
                continue;
            }

            foreach ($constructorParams as $ctorParam) {
                $result[] = $ctorParam;
            }
        }

        return $result;
    }

    /** @return list<ReflectionParameter>|null */
    private function expandInputParameter(ReflectionParameter $parameter): array|null
    {
        $type = $parameter->getType();
        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $className = $type->getName();
        if (! class_exists($className)) {
            return null;
        }

        $refClass = new ReflectionClass($className);
        $constructor = $refClass->getConstructor();
        if ($constructor === null) {
            return null;
        }

        return $constructor->getParameters();
    }
}
