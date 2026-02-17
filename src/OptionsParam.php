<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\Resource\ResourceInterface;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function strtoupper;
use function substr;

/**
 * Extracts parameter metadata from OPTIONS response, with ReflectionMethod fallback
 */
final class OptionsParam
{
    public function __construct(
        private readonly ResourceInterface $resource,
        private readonly string $scheme,
    ) {
    }

    /** @return list<ParamMeta> */
    public function __invoke(string $uriPath, string $requestMethod, ?ReflectionMethod $method = null): array
    {
        $httpMethod = strtoupper(substr($requestMethod, 2));
        $optionsBody = $this->fetchOptionsBody($uriPath);
        if ($optionsBody === null || ! isset($optionsBody[$httpMethod])) {
            return $method instanceof ReflectionMethod ? $this->fromReflection($method) : [];
        }

        return $this->parseMethodParams($optionsBody[$httpMethod]);
    }

    /** @return array<mixed>|null */
    private function fetchOptionsBody(string $uriPath): ?array
    {
        $scheme = $this->scheme === '*' ? 'app' : $this->scheme;
        $uri = "{$scheme}://self{$uriPath}";

        try {
            $ro = $this->resource->options($uri);
        } catch (Throwable) {
            return null;
        }

        /** @var array<mixed>|false|null $body */
        $body = json_decode((string) $ro->view, true);

        return is_array($body) ? $body : null;
    }

    /** @return list<ParamMeta> */
    private function parseMethodParams(mixed $methodData): array
    {
        if (! is_array($methodData)) {
            return []; // @codeCoverageIgnore
        }

        /** @var mixed $request */
        $request = $methodData['request'] ?? [];
        if (! is_array($request)) {
            return []; // @codeCoverageIgnore
        }

        /** @var mixed $parameters */
        $parameters = $request['parameters'] ?? [];
        if (! is_array($parameters)) {
            return []; // @codeCoverageIgnore
        }

        /** @var mixed $requiredRaw */
        $requiredRaw = $request['required'] ?? [];
        /** @var list<string> $required */
        $required = is_array($requiredRaw) ? $requiredRaw : [];

        return $this->buildParamMetas($parameters, $required);
    }

    /**
     * @param array<mixed> $parameters
     * @param list<string> $required
     *
     * @return list<ParamMeta>
     */
    private function buildParamMetas(array $parameters, array $required): array
    {
        $params = [];
        /** @var mixed $meta */
        foreach ($parameters as $name => $meta) {
            if (! is_string($name) || ! is_array($meta)) {
                continue; // @codeCoverageIgnore
            }

            $type = isset($meta['type']) && is_string($meta['type']) ? $meta['type'] : '';
            $default = isset($meta['default']) && is_string($meta['default']) ? $meta['default'] : '';

            $params[] = new ParamMeta(
                name: $name,
                type: $type,
                isOptional: ! in_array($name, $required, true),
                default: $default,
            );
        }

        return $params;
    }

    /**
     * Fallback: extract parameters directly from ReflectionMethod
     *
     * @return list<ParamMeta>
     */
    private function fromReflection(ReflectionMethod $method): array
    {
        $params = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : '';
            $default = $this->getDefaultString($parameter);

            $params[] = new ParamMeta(
                name: $parameter->getName(),
                type: $typeName,
                isOptional: $parameter->isOptional(),
                default: $default,
            );
        }

        return $params;
    }

    private function getDefaultString(\ReflectionParameter $parameter): string
    {
        if (! $parameter->isDefaultValueAvailable()) {
            return '';
        }

        /** @var mixed $defaultValue */
        $defaultValue = $parameter->getDefaultValue();
        if (is_array($defaultValue)) {
            return '[]';
        }

        if (is_string($defaultValue) || is_int($defaultValue) || is_float($defaultValue) || is_bool($defaultValue)) {
            return (string) $defaultValue;
        }

        return '';
    }
}
