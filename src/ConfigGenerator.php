<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\ComposerJsonNotFoundException;
use BEAR\ApiDoc\Exception\InvalidComposerJsonException;

use function array_key_first;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_array;
use function json_decode;
use function rtrim;
use function sprintf;

/** @codeCoverageIgnore */
final class ConfigGenerator
{
    private const TEMPLATE = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<apidoc
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="vendor/bear/apidoc/apidoc.xsd">
    <appName>%s</appName>
    <scheme>app</scheme>
    <docDir>docs</docDir>
    <!-- format: html | md | openapi | llms -->
    <format>html</format>
    <title>%s API Doc</title>%s
    <links>
        <link rel="BEAR.Sunday" href="https://bearsunday.github.io/"/>
    </links>
</apidoc>
XML;

    private const OUTPUT_FILE = 'apidoc.xml';

    public function __invoke(): string
    {
        $composerJsonPath = $this->findComposerJson();
        $composerInfo = $this->parseComposerJson($composerJsonPath);
        $projectName = $this->getProjectName($composerInfo['appName']);
        $descriptionElement = $composerInfo['description'] !== ''
            ? sprintf("\n    <description>%s</description>", $composerInfo['description'])
            : '';
        $xml = sprintf(self::TEMPLATE, $composerInfo['appName'], $projectName, $descriptionElement);

        $outputPath = dirname($composerJsonPath) . '/' . self::OUTPUT_FILE;
        if (file_exists($outputPath)) {
            return sprintf('ApiDoc config already exists: %s', $outputPath);
        }

        file_put_contents($outputPath, $xml);

        return sprintf('ApiDoc config created: %s', $outputPath);
    }

    private function findComposerJson(): string
    {
        $dir = getcwd();
        if ($dir === false) {
            throw new ComposerJsonNotFoundException('Cannot get current working directory');
        }

        $path = $dir . '/composer.json';
        if (! file_exists($path)) {
            throw new ComposerJsonNotFoundException('composer.json not found in current directory');
        }

        return $path;
    }

    /** @return array{appName: string, description: string} */
    private function parseComposerJson(string $composerJsonPath): array
    {
        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            throw new InvalidComposerJsonException('Cannot read composer.json');
        }

        /** @var array{autoload?: array{psr-4?: array<string, string>}, description?: string}|null $json */
        $json = json_decode($content, true);
        if (! is_array($json)) {
            throw new InvalidComposerJsonException('Invalid composer.json format');
        }

        $psr4 = $json['autoload']['psr-4'] ?? null;
        if (! is_array($psr4) || $psr4 === []) {
            throw new InvalidComposerJsonException('No PSR-4 autoload configuration found');
        }

        $namespace = array_key_first($psr4);
        $description = $json['description'] ?? '';

        return [
            'appName' => rtrim($namespace, '\\'),
            'description' => $description,
        ];
    }

    /**
     * Extract project name from namespace (e.g., 'Hpplus\Maquia' -> 'Maquia')
     */
    private function getProjectName(string $appName): string
    {
        $parts = explode('\\', $appName);

        return $parts[count($parts) - 1];
    }
}
