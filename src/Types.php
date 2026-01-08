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
 *
 * @psalm-type ParameterLocation = 'path'|'query'|'header'|'cookie'
 * @psalm-type PathParams = list<string>
 * @psalm-type OperationBase = array{operationId?: string, summary?: string, description?: string}
 * @psalm-type SchemaRef = array{'$ref': string}
 * @psalm-type OpenApiSchema = array<string, mixed>
 * @psalm-type OpenApiParameter = array{name: string, in: ParameterLocation, required: bool, schema: array{type: string}, description?: string, example?: mixed}
 * @psalm-type OpenApiResponse = array{description: string, content?: array<string, array{schema: SchemaRef}>}
 * @psalm-type OpenApiResponses = array<int|string, OpenApiResponse>
 * @psalm-type OpenApiOperation = array{responses: OpenApiResponses, summary?: string, description?: string, operationId?: string, parameters?: list<OpenApiParameter>}
 * @psalm-type OpenApiPathItem = array<string, OpenApiOperation>
 * @psalm-type OpenApiInfo = array{title: string, description: string, version: string}
 * @psalm-type OpenApiComponents = array{schemas: array<string, OpenApiSchema>}
 * @psalm-type OpenApiSpec = array{openapi: string, info: OpenApiInfo, paths: array<string, OpenApiPathItem>, components: OpenApiComponents}
 * @psalm-type HtmlParam = array{name: string, type: string, description: string, required: bool, example: string, constraints: array<string, mixed>, alps: string|null}
 * @psalm-type HtmlMethod = array{params: array<HtmlParam>, response: string|null, summary: string, description: string, embeds: array<mixed>, links: array<mixed>, alps: array<string>}
 * @psalm-type HtmlProperty = array{name: string, type: string, description: string, example: string|null, format: string|null, constraints: array<string, mixed>, ref: string|null}
 * @psalm-type HtmlObject = array{name: string, properties: array<HtmlProperty>, arrayItemType: string|null}
 * @psalm-type HtmlRelation = array{rel: string, href: string, title: string}
 * @psalm-type HtmlObjectRelations = array<string, array{embeds: array<HtmlRelation>, links: array<HtmlRelation>}>
 * @psalm-type DocLink = array{rel: string, href: string}
 */
final class Types
{
}
