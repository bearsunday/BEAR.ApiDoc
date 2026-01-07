<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

/**
 * BEAR.ApiDoc Domain Types for Psalm/PHPStan
 *
 * OpenAPI 3.1 Specification Types
 * Types are ordered so dependencies are defined before use.
 *
 * Primitive Types
 * @psalm-type ParameterLocation = 'path'|'query'|'header'|'cookie'
 * @psalm-type PathParams = list<string>
 * @psalm-type OperationBase = array{operationId?: string, summary?: string, description?: string}
 *
 * Schema Types
 * @psalm-type SchemaRef = array{'$ref': string}
 * @psalm-type OpenApiSchema = array<string, mixed>
 *
 * Parameter Types
 * @psalm-type OpenApiParameter = array{name: string, in: ParameterLocation, required: bool, schema: array{type: string}, description?: string, example?: mixed}
 *
 * Response Types
 * @psalm-type OpenApiResponse = array{description: string, content?: array<string, array{schema: SchemaRef}>}
 * @psalm-type OpenApiResponses = array<string, OpenApiResponse>
 *
 * Operation Types
 * @psalm-type OpenApiOperation = array{responses: OpenApiResponses, summary?: string, description?: string, operationId?: string, parameters?: list<OpenApiParameter>}
 * @psalm-type OpenApiPathItem = array<string, OpenApiOperation>
 *
 * Root Document Types
 * @psalm-type OpenApiInfo = array{title: string, description: string, version: string}
 * @psalm-type OpenApiComponents = array{schemas: array<string, OpenApiSchema>}
 * @psalm-type OpenApiSpec = array{openapi: string, info: OpenApiInfo, paths: array<string, OpenApiPathItem>, components: OpenApiComponents}
 */
final class Types
{
}
