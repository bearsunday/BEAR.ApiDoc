<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use function in_array;

/**
 * @psalm-import-type OpenApiOperationPartial from Types
 * @psalm-import-type OpenApiParameter from Types
 * @psalm-import-type OpenApiParameterSchema from Types
 * @psalm-import-type OpenApiRequestBody from Types
 * @psalm-import-type ParameterLocation from Types
 * @psalm-import-type PathParams from Types
 */
final class OpenApiInputBuilder
{
    /**
     * @param OpenApiOperationPartial $operation
     * @param PathParams              $pathParams
     *
     * @return OpenApiOperationPartial
     */
    public function __invoke(ReflectionMethod $method, string $httpMethod, array $operation, ?Schema $schema, array $pathParams): array
    {
        $methodParams = (new InputParamExpander())($method);
        if ($this->usesRequestBody($httpMethod)) {
            $operation = $this->withRequestBody($methodParams, $schema, $operation, $pathParams);
        } else {
            $operation = $this->withParameters($methodParams, $schema, $operation, $pathParams);
        }

        return $this->ensurePathParameters($operation, $pathParams);
    }

    private function usesRequestBody(string $httpMethod): bool
    {
        return in_array($httpMethod, ['post', 'put', 'patch'], true);
    }

    /**
     * @param list<ReflectionParameter> $methodParams
     * @param OpenApiOperationPartial   $operation
     * @param PathParams                $pathParams
     *
     * @return OpenApiOperationPartial
     */
    private function withRequestBody(array $methodParams, ?Schema $schema, array $operation, array $pathParams): array
    {
        /** @var list<OpenApiParameter> $parameters */
        $parameters = [];
        foreach ($methodParams as $param) {
            $paramName = $param->getName();
            if (! in_array($paramName, $pathParams, true)) {
                continue;
            }

            $parameters[] = $this->createParameterObject(
                $param,
                $schema?->props[$paramName] ?? null,
                'path',
            );
        }

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $requestBody = $this->createRequestBody($methodParams, $schema, $pathParams);
        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        return $operation;
    }

    /**
     * @param list<ReflectionParameter> $methodParams
     * @param OpenApiOperationPartial   $operation
     * @param PathParams                $pathParams
     *
     * @return OpenApiOperationPartial
     */
    private function withParameters(array $methodParams, ?Schema $schema, array $operation, array $pathParams): array
    {
        /** @var list<OpenApiParameter> $parameters */
        $parameters = [];
        foreach ($methodParams as $param) {
            $paramName = $param->getName();
            $isPathParam = in_array($paramName, $pathParams, true);
            /** @var ParameterLocation $location */
            $location = $isPathParam ? 'path' : 'query';
            $parameters[] = $this->createParameterObject(
                $param,
                $schema?->props[$paramName] ?? null,
                $location,
            );
        }

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        return $operation;
    }

    /**
     * @param list<ReflectionParameter> $methodParams
     * @param PathParams                $pathParams
     *
     * @return OpenApiRequestBody|null
     */
    private function createRequestBody(array $methodParams, ?Schema $schema, array $pathParams): ?array
    {
        /** @var array<string, OpenApiParameterSchema> $properties */
        $properties = [];
        /** @var list<string> $required */
        $required = [];
        foreach ($methodParams as $param) {
            $paramName = $param->getName();
            if (in_array($paramName, $pathParams, true)) {
                continue;
            }

            $properties[$paramName] = $this->createParameterSchema($param, $schema?->props[$paramName] ?? null);
            if (! $param->isOptional()) {
                $required[] = $paramName;
            }
        }

        if ($properties === []) {
            return null;
        }

        $bodySchema = [
            'type' => 'object',
            'properties' => $properties,
        ];
        if ($required !== []) {
            $bodySchema['required'] = $required;
        }

        /** @var OpenApiRequestBody $requestBody */
        $requestBody = [
            'content' => [
                'application/json' => ['schema' => $bodySchema],
            ],
        ];
        if ($required !== []) {
            $requestBody['required'] = true;
        }

        return $requestBody;
    }

    /**
     * @param ParameterLocation $location
     *
     * @return OpenApiParameter
     */
    private function createParameterObject(ReflectionParameter $param, ?SchemaProp $paramSchema, string $location): array
    {
        /** @var OpenApiParameter $parameter */
        $parameter = [
            'name' => $param->getName(),
            'in' => $location,
            'required' => $location === 'path' || ! $param->isOptional(),
            'schema' => ['type' => $this->getOpenApiType($param)],
        ];

        $description = $this->getParameterDescription($param, $paramSchema);
        if ($description !== '') {
            $parameter['description'] = $description;
        }

        if ($paramSchema instanceof SchemaProp && $paramSchema->example !== '') {
            $parameter['example'] = $paramSchema->example;
        }

        return $parameter;
    }

    /** @return OpenApiParameterSchema */
    private function createParameterSchema(ReflectionParameter $param, ?SchemaProp $paramSchema): array
    {
        /** @var OpenApiParameterSchema $schema */
        $schema = ['type' => $this->getOpenApiType($param)];

        $description = $this->getParameterDescription($param, $paramSchema);
        if ($description !== '') {
            $schema['description'] = $description;
        }

        if ($paramSchema instanceof SchemaProp && $paramSchema->example !== '') {
            $schema['example'] = $paramSchema->example;
        }

        return $schema;
    }

    private function getParameterDescription(ReflectionParameter $param, ?SchemaProp $paramSchema): string
    {
        $description = $paramSchema instanceof SchemaProp ? $paramSchema->description : '';
        if ($description === '' && $param instanceof DescribedInputParam) {
            return $param->description;
        }

        return $description;
    }

    private function getOpenApiType(ReflectionParameter $param): string
    {
        $paramType = $param->getType();
        $typeName = $paramType instanceof ReflectionNamedType ? $paramType->getName() : 'string';

        return match ($typeName) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    /**
     * @param OpenApiOperationPartial $operation
     * @param PathParams              $pathParams
     *
     * @return OpenApiOperationPartial
     */
    private function ensurePathParameters(array $operation, array $pathParams): array
    {
        if ($pathParams === []) {
            return $operation;
        }

        $parameters = $operation['parameters'] ?? [];
        foreach ($pathParams as $paramName) {
            if ($this->hasPathParameter($parameters, $paramName)) {
                continue;
            }

            $parameters[] = $this->createPathParameter($paramName);
        }

        $operation['parameters'] = $parameters;

        return $operation;
    }

    /** @param list<OpenApiParameter> $parameters */
    private function hasPathParameter(array $parameters, string $paramName): bool
    {
        foreach ($parameters as $parameter) {
            if ($parameter['name'] === $paramName && $parameter['in'] === 'path') {
                return true;
            }
        }

        return false;
    }

    /** @return OpenApiParameter */
    private function createPathParameter(string $paramName): array
    {
        /** @var OpenApiParameter $parameter */
        $parameter = [
            'name' => $paramName,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ];

        return $parameter;
    }
}
