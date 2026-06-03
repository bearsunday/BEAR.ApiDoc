<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_string;
use function preg_match_all;
use function sprintf;
use function strcmp;
use function strtoupper;
use function substr;
use function trim;
use function usort;

/**
 * @psalm-type AuditOperation = array{
 *     path: string,
 *     method: string,
 *     hasResponseSchema: bool,
 *     hasRequestSchema: bool,
 *     hasAlps: bool,
 *     hasClassSummary: bool,
 *     hasMethodSummary: bool,
 *     hasRequestBodyInput: bool
 * }
 * @psalm-type AuditFinding = array{type: string, message: string}
 * @psalm-type AuditFindingGroup = array{method: string, path: string, items: non-empty-list<AuditFinding>}
 * @psalm-type AuditSummary = array{resourceCount: int, operationCount: int, responseSchemaCount: int, requestSchemaCount: int, alpsAttributeCount: int|null}
 */
final class ApiDocAudit
{
    private const METHOD_ORDER = [
        'DELETE' => 0,
        'GET' => 1,
        'PATCH' => 2,
        'POST' => 3,
        'PUT' => 4,
    ];

    public function __construct(private readonly Config $config)
    {
    }

    public function generateMarkdown(): string
    {
        $report = $this->collectReport();
        $summary = $report['summary'];

        $lines = [
            '# API Documentation Audit',
            '',
            '## Summary',
            sprintf('- Resources: %d', $summary['resourceCount']),
            sprintf('- Operations: %d', $summary['operationCount']),
            sprintf('- Operations with response schema: %d', $summary['responseSchemaCount']),
            sprintf('- Operations with request schema: %d', $summary['requestSchemaCount']),
        ];

        if ($summary['alpsAttributeCount'] !== null) {
            $lines[] = sprintf('- Operations with ALPS attributes: %d', $summary['alpsAttributeCount']);
        }

        $lines[] = '';
        $lines[] = '## Findings';

        if ($report['groups'] === []) {
            $lines[] = 'No documentation gaps found.';

            return implode("\n", $lines);
        }

        foreach ($report['groups'] as $group) {
            $lines[] = sprintf('### %s %s', $group['method'], $group['path']);
            foreach ($group['items'] as $finding) {
                $lines[] = sprintf('- %s', $finding['message']);
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function generateHtml(): string
    {
        $report = $this->collectReport();

        return (new AuditHtmlRenderer())->render($report['summary'], $report['groups']);
    }

    /**
     * Single collection used by both formatters, so summary counts and finding
     * grouping live in one place.
     *
     * @return array{summary: AuditSummary, groups: list<AuditFindingGroup>}
     */
    private function collectReport(): array
    {
        $operations = $this->collectOperations();

        /** @var list<AuditFindingGroup> $groups */
        $groups = [];
        foreach ($operations as $operation) {
            $items = $this->findingsFor($operation);
            if ($items === []) {
                continue;
            }

            $groups[] = [
                'method' => $operation['method'],
                'path' => $operation['path'],
                'items' => $items,
            ];
        }

        $summary = [
            'resourceCount' => count($this->config->resourceFiles),
            'operationCount' => count($operations),
            'responseSchemaCount' => count(array_filter($operations, static fn (array $operation): bool => $operation['hasResponseSchema'])),
            'requestSchemaCount' => count(array_filter($operations, static fn (array $operation): bool => $operation['hasRequestSchema'])),
            'alpsAttributeCount' => $this->alpsEnabled()
                ? count(array_filter($operations, static fn (array $operation): bool => $operation['hasAlps']))
                : null,
        ];

        return ['summary' => $summary, 'groups' => $groups];
    }

    /** @return list<AuditOperation> */
    private function collectOperations(): array
    {
        /** @var list<AuditOperation> $operations */
        $operations = [];

        foreach ($this->config->resourceFiles as $meta) {
            $path = $this->config->routes[$meta->uriPath] ?? $meta->uriPath;
            /** @var ReflectionClass<object> $class */
            $class = new ReflectionClass($meta->class);
            $hasClassSummary = $this->hasSummary($class->getDocComment());
            $hasClassAlps = $class->getAttributes(Alps::class) !== [];

            foreach ($class->getMethods() as $method) {
                $methodName = $method->getName();
                if (! in_array($methodName, ['onGet', 'onPut', 'onPost', 'onPatch', 'onDelete'], true)) {
                    continue;
                }

                $httpMethod = strtoupper(substr($methodName, 2));
                [$hasResponseSchema, $hasRequestSchema] = $this->jsonSchemaCoverage($method);

                $operations[] = [
                    'path' => $path,
                    'method' => $httpMethod,
                    'hasResponseSchema' => $hasResponseSchema,
                    'hasRequestSchema' => $hasRequestSchema,
                    'hasAlps' => $hasClassAlps || $method->getAttributes(Alps::class) !== [],
                    'hasClassSummary' => $hasClassSummary,
                    'hasMethodSummary' => $this->hasSummary($method->getDocComment()),
                    'hasRequestBodyInput' => $this->hasRequestBodyInput($method, $httpMethod, $path),
                ];
            }
        }

        usort($operations, self::compareOperation(...));

        return $operations;
    }

    /**
     * @param AuditOperation $left
     * @param AuditOperation $right
     */
    private static function compareOperation(array $left, array $right): int
    {
        $pathComparison = strcmp($left['path'], $right['path']);
        if ($pathComparison !== 0) {
            return $pathComparison;
        }

        return (self::METHOD_ORDER[$left['method']] ?? 99) <=> (self::METHOD_ORDER[$right['method']] ?? 99);
    }

    /** @return array{bool, bool} */
    private function jsonSchemaCoverage(ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(JsonSchema::class);
        if ($attributes === []) {
            return [false, false];
        }

        $jsonSchema = $attributes[0]->newInstance();

        return [$jsonSchema->schema !== '', $jsonSchema->params !== ''];
    }

    private function hasSummary(string|false $docComment): bool
    {
        if (! is_string($docComment) || trim($docComment) === '') {
            return false;
        }

        try {
            $docBlock = DocBlockFactory::createInstance()->create($docComment);
        } catch (Throwable) { // @codeCoverageIgnore
            return false; // @codeCoverageIgnore
        }

        return trim($docBlock->getSummary()) !== '';
    }

    private function hasRequestBodyInput(ReflectionMethod $method, string $httpMethod, string $path): bool
    {
        if (! in_array($httpMethod, ['POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        $pathParams = $matches[1];
        $inputParams = array_map(
            static fn ($param): string => $param->getName(),
            (new InputParamExpander())($method),
        );

        return array_values(array_filter(
            $inputParams,
            static fn (string $param): bool => ! in_array($param, $pathParams, true),
        )) !== [];
    }

    /**
     * @param AuditOperation $operation
     *
     * @return list<AuditFinding>
     */
    private function findingsFor(array $operation): array
    {
        $findings = [];
        if (! $operation['hasResponseSchema']) {
            $findings[] = ['type' => 'response-schema', 'message' => 'Missing response schema.'];
        }

        if ($operation['hasRequestBodyInput'] && ! $operation['hasRequestSchema']) {
            $findings[] = ['type' => 'request-schema', 'message' => 'Missing request schema for non-path body input.'];
        }

        if (! $operation['hasClassSummary']) {
            $findings[] = ['type' => 'class-summary', 'message' => 'Missing resource class summary.'];
        }

        if (! $operation['hasMethodSummary']) {
            $findings[] = ['type' => 'operation-summary', 'message' => 'Missing operation summary.'];
        }

        if ($this->alpsEnabled() && ! $operation['hasAlps']) {
            $findings[] = ['type' => 'alps', 'message' => 'Missing ALPS attribute.'];
        }

        return $findings;
    }

    private function alpsEnabled(): bool
    {
        return $this->config->alps !== '' && $this->config->alps !== '0';
    }
}
