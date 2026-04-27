<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ReflectionMethod;
use ReflectionParameter;

use function assert;

/**
 * @psalm-immutable
 * @psalm-suppress PropertyNotSetInConstructor ReflectionParameter initializes internal state.
 */
final class DescribedInputParam extends ReflectionParameter
{
    public function __construct(ReflectionParameter $parameter, public readonly string $description)
    {
        // #[Input] only ever decorates resource method parameters, so the
        // declaring function is always a ReflectionMethod here.
        $function = $parameter->getDeclaringFunction();
        assert($function instanceof ReflectionMethod);

        parent::__construct([$function->getDeclaringClass()->getName(), $function->getName()], $parameter->getName());
    }
}
