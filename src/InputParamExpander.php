<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Ray\InputQuery\Attribute\Input;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

use function class_exists;
use function trim;

final class InputParamExpander
{
    private readonly DocBlockFactoryInterface $docBlockFactory;

    public function __construct(?DocBlockFactoryInterface $docBlockFactory = null)
    {
        $this->docBlockFactory = $docBlockFactory ?? DocBlockFactory::createInstance();
    }

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
            return null; // @codeCoverageIgnore
        }

        $refClass = new ReflectionClass($className);
        $constructor = $refClass->getConstructor();
        if ($constructor === null) {
            return null;
        }

        $constructorParams = [];
        foreach ($constructor->getParameters() as $constructorParam) {
            $description = $this->getPromotedPropertyDescription($refClass, $constructorParam);
            $constructorParams[] = $description === null
                ? $constructorParam
                : new DescribedInputParam($constructorParam, $description);
        }

        return $constructorParams;
    }

    /** @param ReflectionClass<object> $refClass */
    private function getPromotedPropertyDescription(ReflectionClass $refClass, ReflectionParameter $parameter): string|null
    {
        if (! $parameter->isPromoted() || ! $refClass->hasProperty($parameter->getName())) {
            return null;
        }

        $docComment = $refClass->getProperty($parameter->getName())->getDocComment();
        if ($docComment === false) {
            return null;
        }

        try {
            $docblock = $this->docBlockFactory->create($docComment);
        } catch (Throwable) {
            // Malformed docblock — treat the parameter as undocumented rather
            // than aborting the whole documentation generation.
            return null;
        }

        $summary = trim($docblock->getSummary());
        $description = trim((string) $docblock->getDescription());
        if ($summary === '') {
            return $description === '' ? null : $description;
        }

        if ($description === '') {
            return $summary;
        }

        return $summary . "\n\n" . $description;
    }
}
