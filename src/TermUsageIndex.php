<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SimpleXMLElement;
use SplFileInfo;

use function array_key_exists;
use function array_keys;
use function assert;
use function basename;
use function count;
use function file_exists;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function ksort;
use function pathinfo;
use function round;
use function rtrim;
use function simplexml_load_file;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtoupper;
use function substr;
use function trim;
use function usort;

use const PATHINFO_EXTENSION;
use const PHP_EOL;

/**
 * @psalm-type AlpsDescriptor = array{title?: string, def?: string, doc?: string}
 * @psalm-type TermUsageData = array{apiUsages: array<string, array<string, true>>, reservedUsages: array<string, array<string, true>>, alpsDescriptors: array<string, AlpsDescriptor>, matchedAlpsDescriptorCount: int}
 */
final class TermUsageIndex
{
    /** @var array<string, array<string, true>> */
    private array $apiUsages = [];

    /** @var array<string, array<string, true>> */
    private array $reservedUsages = [];

    /** @var array<string, AlpsDescriptor> */
    private array $alpsDescriptors = [];

    public function __construct(
        private readonly Config $config,
        private readonly InputParamExpander $inputParamExpander = new InputParamExpander(),
        private readonly JsonFile $jsonFile = new JsonFile(),
    ) {
    }

    public function generateMarkdown(): string
    {
        $data = $this->collectData();
        $apiUsages = $data['apiUsages'];
        $reservedUsages = $data['reservedUsages'];
        $alpsDescriptors = $data['alpsDescriptors'];
        $matchedAlpsDescriptorCount = $data['matchedAlpsDescriptorCount'];

        $lines = [
            '# Term Usage Index',
            '',
            'This index reports lexical identifier matches only; it does not prove semantic equivalence.',
            '',
            '## Summary',
            '',
            sprintf('- Terms used in API: %d', count($apiUsages)),
            sprintf('- Terms with same-name ALPS descriptor: %d', $matchedAlpsDescriptorCount),
            sprintf('- Lexical ALPS coverage: %s%%', $this->coveragePercent(count($apiUsages), $matchedAlpsDescriptorCount)),
            sprintf('- Reserved representation fields: %d', count($reservedUsages)),
            '- ☑︎ = ALPS descriptor binding',
            '',
            '## Terms',
            '',
        ];

        foreach ($apiUsages as $term => $usages) {
            $descriptor = $alpsDescriptors[$term] ?? null;
            $lines[] = sprintf('### `%s`%s', $term, $descriptor !== null ? ' ☑︎' : '');
            $lines[] = '';

            if ($descriptor !== null) {
                foreach (['title', 'def', 'doc'] as $field) {
                    $value = $descriptor[$field] ?? '';
                    if ($value === '') {
                        continue;
                    }

                    $lines[] = sprintf('- %s: %s', $field, $value);
                }
            }

            $lines[] = '- usages:';
            foreach (array_keys($usages) as $usage) {
                $lines[] = sprintf('  - %s', $usage);
            }

            $lines[] = '';
        }

        if ($reservedUsages !== []) {
            $lines[] = '## Reserved Representation Fields';
            $lines[] = '';
            $lines[] = 'Leading-underscore fields are listed separately because they usually belong to the representation format rather than the API domain vocabulary.';
            $lines[] = '';

            foreach ($reservedUsages as $term => $usages) {
                $lines[] = sprintf('### Field: `%s`', $term);
                $lines[] = '';
                $lines[] = '- usages:';
                foreach (array_keys($usages) as $usage) {
                    $lines[] = sprintf('  - %s', $usage);
                }

                $lines[] = '';
            }
        }

        return rtrim(implode(PHP_EOL, $lines)) . PHP_EOL;
    }

    public function generateHtml(): string
    {
        $data = $this->collectData();
        $apiUsages = $data['apiUsages'];
        $reservedUsages = $data['reservedUsages'];
        $alpsDescriptors = $data['alpsDescriptors'];
        $matchedAlpsDescriptorCount = $data['matchedAlpsDescriptorCount'];
        $coverage = $this->coveragePercent(count($apiUsages), $matchedAlpsDescriptorCount);

        return (new TermUsageHtmlRenderer())->render(
            $apiUsages,
            $reservedUsages,
            $alpsDescriptors,
            $matchedAlpsDescriptorCount,
            $coverage,
        );
    }

    /** @return TermUsageData */
    private function collectData(): array
    {
        $this->apiUsages = [];
        $this->reservedUsages = [];
        $this->alpsDescriptors = [];
        $this->collectParameterUsages();
        $this->collectSchemaPropertyUsages($this->config->requestSchemaDir);
        $this->collectSchemaPropertyUsages($this->config->responseSchemaDir);
        $this->collectAlpsDescriptors();

        /** @var array<string, array<string, true>> $apiUsages */
        $apiUsages = $this->apiUsages;
        /** @var array<string, array<string, true>> $reservedUsages */
        $reservedUsages = $this->reservedUsages;
        /** @var array<string, AlpsDescriptor> $alpsDescriptors */
        $alpsDescriptors = $this->alpsDescriptors;
        ksort($apiUsages);
        ksort($reservedUsages);
        ksort($alpsDescriptors);

        return [
            'apiUsages' => $apiUsages,
            'reservedUsages' => $reservedUsages,
            'alpsDescriptors' => $alpsDescriptors,
            'matchedAlpsDescriptorCount' => $this->matchedAlpsDescriptorCount($apiUsages, $alpsDescriptors),
        ];
    }

    private function collectParameterUsages(): void
    {
        foreach ($this->sortedResourceFiles() as $meta) {
            $path = $this->config->routes[$meta->uriPath] ?? $meta->uriPath;
            $class = new ReflectionClass($meta->class);
            foreach ($class->getMethods() as $method) {
                if (! $this->isResourceMethod($method)) {
                    continue;
                }

                $httpMethod = strtoupper(substr($method->getName(), 2));
                foreach (($this->inputParamExpander)($method) as $parameter) {
                    $term = $parameter->getName();
                    $this->addUsage($term, sprintf('parameter: %s %s {%s}', $httpMethod, $path, $term));
                }
            }
        }
    }

    private function collectSchemaPropertyUsages(string $schemaDir): void
    {
        if ($schemaDir === '' || $schemaDir === '0' || ! is_dir($schemaDir)) {
            return;
        }

        foreach ($this->schemaFiles($schemaDir) as $schemaFile) {
            $schema = $this->jsonFile->assoc($schemaFile);
            $schemaName = basename($schemaFile);
            foreach ($this->schemaProperties($schema) as $pointer => $property) {
                $this->addUsage($property, sprintf('schema property: %s#%s', $schemaName, $pointer));
            }
        }
    }

    private function addUsage(string $term, string $usage): void
    {
        if (str_starts_with($term, '_')) {
            $this->reservedUsages[$term][$usage] = true;

            return;
        }

        $this->apiUsages[$term][$usage] = true;
    }

    private function collectAlpsDescriptors(): void
    {
        if ($this->config->alps === '' || $this->config->alps === '0' || ! file_exists($this->config->alps)) {
            return;
        }

        if (pathinfo($this->config->alps, PATHINFO_EXTENSION) === 'json') {
            $profile = $this->jsonFile->assoc($this->config->alps);
            $this->collectAlpsDescriptorsFromArray($profile);

            return;
        }

        $xml = @simplexml_load_file($this->config->alps);
        if (! $xml instanceof SimpleXMLElement) {
            return;
        }

        $this->collectAlpsDescriptorsFromXml($xml);
    }

    /** @return list<object{uriPath: string, class: class-string}> */
    private function sortedResourceFiles(): array
    {
        $resourceFiles = $this->config->resourceFiles;
        usort(
            $resourceFiles,
            static fn (object $a, object $b): int => [$a->uriPath, $a->class] <=> [$b->uriPath, $b->class],
        );

        /** @var list<object{uriPath: string, class: class-string}> $resourceFiles */
        return $resourceFiles;
    }

    private function isResourceMethod(ReflectionMethod $method): bool
    {
        return $method->getName() === 'onGet'
            || $method->getName() === 'onPut'
            || $method->getName() === 'onPost'
            || $method->getName() === 'onPatch'
            || $method->getName() === 'onDelete';
    }

    /** @return list<string> */
    private function schemaFiles(string $schemaDir): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($schemaDir, FilesystemIterator::SKIP_DOTS));
        /** @var list<string> $files */
        $files = [];
        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            $pathname = $file->getPathname();
            if (is_file($pathname) && str_ends_with($pathname, '.json')) {
                $files[] = $pathname;
            }
        }

        usort($files, static fn (mixed $a, mixed $b): int => [basename((string) $a), (string) $a] <=> [basename((string) $b), (string) $b]);

        return $files;
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return array<string, string>
     */
    private function schemaProperties(array $schema, string $basePointer = ''): array
    {
        $properties = [];
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            /** @var array<array-key, mixed> $rawProperties */
            $rawProperties = $schema['properties'];
            foreach (array_keys($rawProperties) as $name) {
                $property = (string) $name;
                $pointer = $basePointer . '/properties/' . $this->jsonPointerToken($property);
                $properties[$pointer] = $property;
                if (is_array($rawProperties[$name])) {
                    $properties += $this->schemaProperties($rawProperties[$name], $pointer);
                }
            }
        }

        foreach ($schema as $key => $value) {
            if ($key === 'properties' || ! is_array($value)) {
                continue;
            }

            /** @var array<array-key, mixed> $schemaValue */
            $schemaValue = $value;
            $properties += $this->schemaProperties($schemaValue, $basePointer . '/' . $this->jsonPointerToken((string) $key));
        }

        ksort($properties);

        return $properties;
    }

    private function jsonPointerToken(string $token): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $token);
    }

    /** @param array<array-key, mixed> $node */
    private function collectAlpsDescriptorsFromArray(array $node): void
    {
        if (isset($node['id']) && is_string($node['id'])) {
            $this->alpsDescriptors[$node['id']] = $this->alpsDescriptorFromArray($node);
        }

        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }

            /** @var array<array-key, mixed> $value */
            $this->collectAlpsDescriptorsFromArray($value);
        }
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return AlpsDescriptor
     */
    private function alpsDescriptorFromArray(array $node): array
    {
        $descriptor = [];
        if (isset($node['title']) && is_string($node['title']) && $node['title'] !== '') {
            $descriptor['title'] = $node['title'];
        }

        if (isset($node['def']) && is_string($node['def']) && $node['def'] !== '') {
            $descriptor['def'] = $node['def'];
        }

        $doc = $this->docFromArray($node['doc'] ?? null);
        if ($doc !== '') {
            $descriptor['doc'] = $doc;
        }

        return $descriptor;
    }

    private function docFromArray(mixed $doc): string
    {
        if (is_string($doc)) {
            return trim($doc);
        }

        if (is_array($doc) && isset($doc['value']) && is_string($doc['value'])) {
            return trim($doc['value']);
        }

        return '';
    }

    private function collectAlpsDescriptorsFromXml(SimpleXMLElement $node): void
    {
        $id = (string) $node['id'];
        if ($id !== '') {
            $this->alpsDescriptors[$id] = $this->alpsDescriptorFromXml($node);
        }

        foreach ($node->children() ?? [] as $child) {
            /** @var SimpleXMLElement $child */
            $this->collectAlpsDescriptorsFromXml($child);
        }
    }

    /** @return AlpsDescriptor */
    private function alpsDescriptorFromXml(SimpleXMLElement $node): array
    {
        $descriptor = [];
        $title = (string) $node['title'];
        if ($title !== '') {
            $descriptor['title'] = $title;
        }

        $def = (string) $node['def'];
        if ($def !== '') {
            $descriptor['def'] = $def;
        }

        foreach ($node->children() ?? [] as $child) {
            if ($child->getName() !== 'doc') {
                continue;
            }

            $doc = trim((string) $child);
            if ($doc !== '') {
                $descriptor['doc'] = $doc;
            }
        }

        return $descriptor;
    }

    /**
     * @param array<string, array<string, true>> $apiUsages
     * @param array<string, AlpsDescriptor>      $alpsDescriptors
     */
    private function matchedAlpsDescriptorCount(array $apiUsages, array $alpsDescriptors): int
    {
        $count = 0;
        foreach (array_keys($apiUsages) as $term) {
            if (array_key_exists($term, $alpsDescriptors)) {
                $count++;
            }
        }

        return $count;
    }

    private function coveragePercent(int $total, int $matched): string
    {
        if ($total === 0) {
            return '0';
        }

        $coverage = round((float) $matched / (float) $total * 100.0, 1);

        return sprintf('%g', $coverage);
    }
}
