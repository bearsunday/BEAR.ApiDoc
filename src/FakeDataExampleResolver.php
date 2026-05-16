<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use JsonException;

use function array_fill;
use function array_intersect_key;
use function array_is_list;
use function array_shift;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function preg_split;
use function realpath;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function ucfirst;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const PATHINFO_FILENAME;

final readonly class FakeDataExampleResolver
{
    private string $examplesDir;

    public function __construct(
        private string $fakeDataDir,
        private string $docDir,
    ) {
        $this->examplesDir = rtrim($this->docDir, '/') . '/examples';
    }

    public function responseExample(string $schemaFile, ?Schema $schema): ?FakeDataExample
    {
        if ($this->fakeDataDir === '' || $schemaFile === '' || ! $schema instanceof Schema) {
            return null;
        }

        $baseName = $this->baseName($schemaFile);
        $fakeFile = $this->fakeFile($baseName);
        $payload = $this->readPayload($fakeFile);
        if ($payload === null) {
            return null;
        }

        return $this->responseExampleFromPayload($baseName, $fakeFile, $schema, $payload);
    }

    /** @param array<array-key, mixed> $payload */
    private function responseExampleFromPayload(string $baseName, string $fakeFile, Schema $schema, array $payload): ?FakeDataExample
    {
        if ($schema->type === 'array' && array_is_list($payload)) {
            return $this->sourceExample($baseName, $fakeFile);
        }

        if ($schema->type === 'object' && ! array_is_list($payload)) {
            return $this->sourceExample($baseName, $fakeFile);
        }

        if ($schema->type === 'object' && array_is_list($payload)) {
            $firstPayload = $payload[0] ?? null;
            if (! is_array($firstPayload) || array_is_list($firstPayload)) {
                return null;
            }

            /** @var array<string, mixed> $firstItem */
            $firstItem = $firstPayload;

            return $this->derivedExample($baseName, $baseName, $firstItem);
        }

        return null;
    }

    /** @param list<string> $propertyNames */
    public function requestExample(string $requestSchemaFile, ?Schema $requestSchema, string $responseSchemaFile, array $propertyNames): ?FakeDataExample
    {
        if ($this->fakeDataDir === '' || $requestSchemaFile === '' || ! $requestSchema instanceof Schema || $propertyNames === []) {
            return null;
        }

        $requestBaseName = $this->baseName($requestSchemaFile);
        [$fakeFile, $usesFallbackFake] = $this->requestFakeFile($requestBaseName, $responseSchemaFile);

        $payload = $this->readPayload($fakeFile);
        if ($payload === null) {
            return null;
        }

        $source = $this->selectObjectPayload($payload);
        if ($source === null) {
            return null;
        }

        $projected = $this->project($source, $propertyNames);
        if ($projected === []) {
            return null;
        }

        if (! $usesFallbackFake && ! array_is_list($payload) && $projected === $source) {
            return $this->sourceExample($requestBaseName, $fakeFile);
        }

        return $this->derivedExample($requestBaseName, $requestBaseName, $projected);
    }

    /** @return array{0: string, 1: bool} */
    private function requestFakeFile(string $requestBaseName, string $responseSchemaFile): array
    {
        $fakeFile = $this->fakeFile($requestBaseName);
        if (is_file($fakeFile)) {
            return [$fakeFile, false];
        }

        $fallbackBaseName = $this->fallbackResponseBaseName($requestBaseName, $responseSchemaFile);

        return [$this->fakeFile($fallbackBaseName), true];
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function selectObjectPayload(array $payload): ?array
    {
        if (! array_is_list($payload)) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        $firstPayload = $payload[0] ?? null;
        if (! is_array($firstPayload) || array_is_list($firstPayload)) {
            return null;
        }

        /** @var array<string, mixed> $firstPayload */
        return $firstPayload;
    }

    private function sourceExample(string $baseName, string $file): FakeDataExample
    {
        return new FakeDataExample(
            $this->componentName($baseName),
            sprintf('Generated fake %s payload', $this->titleName($baseName)),
            $this->relativePath($this->docDir, $file),
        );
    }

    /** @param array<string, mixed> $payload */
    private function derivedExample(string $componentBaseName, string $fileBaseName, array $payload): ?FakeDataExample
    {
        if (! is_dir($this->examplesDir) && ! mkdir($this->examplesDir, 0777, true) && ! is_dir($this->examplesDir)) {
            return null; // @codeCoverageIgnore
        }

        $file = sprintf('%s/%s.json', $this->examplesDir, $fileBaseName);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($file, $json) === false) {
            return null; // @codeCoverageIgnore
        }

        return new FakeDataExample(
            $this->componentName($componentBaseName),
            sprintf('Generated fake %s payload', $this->titleName($componentBaseName)),
            './examples/' . $fileBaseName . '.json',
        );
    }

    private function fakeFile(string $baseName): string
    {
        return sprintf('%s/%s.json', rtrim($this->fakeDataDir, '/'), $baseName);
    }

    /**
     * @return array<array-key, mixed>|null
     *
     * @psalm-suppress MixedAssignment
     */
    private function readPayload(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $json = @file_get_contents($file);
        if ($json === false || $json === '') {
            return null;
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $propertyNames
     *
     * @return array<string, mixed>
     */
    private function project(array $payload, array $propertyNames): array
    {
        $allowed = [];
        foreach ($propertyNames as $propertyName) {
            $allowed[$propertyName] = true;
        }

        /** @var array<string, mixed> */
        return array_intersect_key($payload, $allowed);
    }

    private function fallbackResponseBaseName(string $requestBaseName, string $responseSchemaFile): string
    {
        if ($responseSchemaFile !== '') {
            return $this->baseName($responseSchemaFile);
        }

        if (str_ends_with($requestBaseName, '.param')) {
            return substr($requestBaseName, 0, -6);
        }

        return $requestBaseName;
    }

    private function baseName(string $schemaFile): string
    {
        return pathinfo($schemaFile, PATHINFO_FILENAME);
    }

    private function componentName(string $baseName): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $baseName) ?: [];
        $name = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $name .= ucfirst($part);
        }

        if ($name === '') {
            $name = 'Payload';
        }

        return $name . 'Fake';
    }

    private function titleName(string $baseName): string
    {
        $title = preg_replace('/[^A-Za-z0-9]+/', ' ', $baseName);

        return $title === null || $title === '' ? 'payload' : $title;
    }

    private function relativePath(string $fromDir, string $toFile): string
    {
        $resolvedFrom = realpath($fromDir);
        $from = $resolvedFrom !== false ? $resolvedFrom : $fromDir;
        $resolvedTo = realpath($toFile);
        $to = $resolvedTo !== false ? $resolvedTo : $toFile;
        $fromParts = explode(DIRECTORY_SEPARATOR, rtrim($from, DIRECTORY_SEPARATOR));
        $toParts = explode(DIRECTORY_SEPARATOR, $to);

        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        $relativeParts = array_fill(0, count($fromParts), '..');
        foreach ($toParts as $part) {
            if ($part !== '') {
                $relativeParts[] = $part;
            }
        }

        $relative = implode('/', $relativeParts);

        return str_starts_with($relative, '.') ? $relative : './' . $relative;
    }
}
