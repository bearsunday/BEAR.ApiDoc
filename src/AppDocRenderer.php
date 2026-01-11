<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function array_keys;
use function count;
use function implode;
use function sprintf;

final class AppDocRenderer
{
    /**
     * @param array<string, array{uri: string, methods: array<string, array{params: string, links: string}>}> $resources
     * @param array<string, array{schema: string, properties: string}>                                        $responses
     * @param array<string, string>                                                                           $queryInterfaces
     * @param array<string, string>                                                                           $sqlFiles
     * @param array<string, string>                                                                           $entities
     * @param array<string, string>                                                                           $routes
     */
    public function render(
        string $appName,
        string $description,
        array $resources,
        array $responses,
        array $queryInterfaces,
        array $sqlFiles,
        array $entities,
        array $routes,
    ): string {
        $output = [];

        // Header
        $output[] = sprintf('# %s', $appName);
        if ($description !== '') {
            $output[] = '';
            $output[] = $description;
        }

        // Routes
        $output[] = '';
        $output[] = $this->renderRoutes($resources, $routes);

        // ResourceObjects
        $output[] = '';
        $output[] = $this->renderResources($resources);

        // Responses (if exist)
        if ($responses !== []) {
            $output[] = '';
            $output[] = $this->renderResponses($responses);
        }

        // Query Interfaces (if exist)
        if ($queryInterfaces !== []) {
            $output[] = '';
            $output[] = $this->renderQueryInterfaces($queryInterfaces);
        }

        // SQL (if exist)
        if ($sqlFiles !== []) {
            $output[] = '';
            $output[] = $this->renderSql($sqlFiles);
        }

        // Entities (if exist)
        if ($entities !== []) {
            $output[] = '';
            $output[] = $this->renderEntities($entities);
        }

        return implode("\n", $output);
    }

    /**
     * @param array<string, array{uri: string, methods: array<string, array{params: string, links: string}>}> $resources
     * @param array<string, string>                                                                           $routes
     */
    private function renderRoutes(array $resources, array $routes): string
    {
        $lines = [];
        $routeCount = count($resources);

        $lines[] = sprintf('## Routes (%d)', $routeCount);
        $lines[] = '';
        $lines[] = '| HTTP Route | Methods | Resource |';
        $lines[] = '|------------|---------|----------|';

        foreach ($resources as $className => $data) {
            $httpRoute = $this->findHttpRoute($data['uri'], $routes);
            $methods = implode(', ', array_keys($data['methods']));
            $lines[] = sprintf('| %s | %s | %s |', $httpRoute, $methods, $className);
        }

        return implode("\n", $lines);
    }

    /** @param array<string, string> $routes */
    private function findHttpRoute(string $resourceUri, array $routes): string
    {
        foreach ($routes as $resPath => $httpPath) {
            if ($resPath === $resourceUri) {
                return $httpPath;
            }
        }

        return $resourceUri;
    }

    /** @param array<string, array{uri: string, methods: array<string, array{params: string, links: string}>}> $resources */
    private function renderResources(array $resources): string
    {
        $lines = [];
        $resourceCount = count($resources);

        $lines[] = sprintf('## ResourceObjects (%d)', $resourceCount);

        foreach ($resources as $className => $data) {
            $lines[] = '';
            $lines[] = sprintf('### %s', $className);
            $lines[] = sprintf('`%s`', $data['uri']);
            $lines[] = '';
            $lines[] = '| Method | Parameters | Links |';
            $lines[] = '|--------|------------|-------|';

            foreach ($data['methods'] as $method => $info) {
                $lines[] = sprintf(
                    '| %s | %s | %s |',
                    $method,
                    $info['params'] !== '' ? $info['params'] : '-',
                    $info['links'],
                );
            }
        }

        return implode("\n", $lines);
    }

    /** @param array<string, array{schema: string, properties: string}> $responses */
    private function renderResponses(array $responses): string
    {
        $lines = [];
        $count = count($responses);

        $lines[] = sprintf('## Responses (%d)', $count);
        $lines[] = '';
        $lines[] = '| Response | Schema | Properties |';
        $lines[] = '|----------|--------|------------|';

        foreach ($responses as $name => $data) {
            $lines[] = sprintf('| %s | %s | %s |', $name, $data['schema'], $data['properties']);
        }

        return implode("\n", $lines);
    }

    /** @param array<string, string> $queryInterfaces */
    private function renderQueryInterfaces(array $queryInterfaces): string
    {
        $lines = [];
        $count = count($queryInterfaces);

        $lines[] = sprintf('## Query Interfaces (%d)', $count);
        $lines[] = '';
        $lines[] = '| Interface | Methods |';
        $lines[] = '|-----------|---------|';

        foreach ($queryInterfaces as $name => $methods) {
            $lines[] = sprintf('| %s | %s |', $name, $methods);
        }

        return implode("\n", $lines);
    }

    /** @param array<string, string> $sqlFiles */
    private function renderSql(array $sqlFiles): string
    {
        $lines = [];
        $count = count($sqlFiles);

        $lines[] = sprintf('## SQL (%d)', $count);
        $lines[] = '';
        $lines[] = '| File | Query |';
        $lines[] = '|------|-------|';

        foreach ($sqlFiles as $file => $query) {
            $lines[] = sprintf('| %s | `%s` |', $file, $query);
        }

        return implode("\n", $lines);
    }

    /** @param array<string, string> $entities */
    private function renderEntities(array $entities): string
    {
        $lines = [];
        $count = count($entities);

        $lines[] = sprintf('## Entities (%d)', $count);
        $lines[] = '';
        $lines[] = '| Entity | Properties |';
        $lines[] = '|--------|------------|';

        foreach ($entities as $name => $properties) {
            $lines[] = sprintf('| %s | %s |', $name, $properties);
        }

        return implode("\n", $lines);
    }
}
