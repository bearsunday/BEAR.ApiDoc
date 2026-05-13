<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\ConfigException;
use BEAR\ApiDoc\Exception\ConfigNotFoundException;
use DOMDocument;
use SimpleXMLElement;

use function dirname;
use function file_exists;
use function file_get_contents;
use function getcwd;
use function is_dir;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function realpath;
use function simplexml_load_string;
use function sprintf;
use function substr;

use const LIBXML_ERR_ERROR;
use const LIBXML_ERR_FATAL;

final class XmlLoader
{
    public function __invoke(string $xmlPath, string $xsdPath): SimpleXMLElement
    {
        $xmlFullPath = $this->locateConfigFile($xmlPath);
        $contents = file_get_contents($xmlFullPath);
        // @codeCoverageIgnoreStart
        if ($contents === false) {
            throw new ConfigException(sprintf('Cannot read XML config: %s', $xmlFullPath));
        }

        // @codeCoverageIgnoreEnd
        $this->validate($contents, $xmlFullPath, $xsdPath);
        $simpleXml = simplexml_load_string($contents);
        // @codeCoverageIgnoreStart
        if (! $simpleXml instanceof SimpleXMLElement) {
            throw new ConfigException(sprintf('Invalid XML config: %s', $xmlFullPath));
        }

        // @codeCoverageIgnoreEnd
        return $simpleXml;
    }

    public function locateConfigFile(string $path): string
    {
        if (file_exists($path)) {
            return $path;
        }

        $cwd = getcwd();
        // @codeCoverageIgnoreStart
        if ($cwd === false) {
            throw new ConfigNotFoundException($path);
        }

        // @codeCoverageIgnoreEnd

        $maybePath = sprintf('%s/%s', $cwd, $path);
        if (file_exists($maybePath) && ! is_dir($maybePath)) {
            // @codeCoverageIgnoreStart
            return $maybePath;
            // @codeCoverageIgnoreEnd
        }

        $found = $this->searchInParentDirectories($path, $cwd);
        if ($found !== null) {
            return $found;
        }

        throw new ConfigNotFoundException($path);
    }

    private function searchInParentDirectories(string $path, string $cwd): ?string
    {
        $realPath = realpath($path);
        $dirPath = $realPath !== false ? $realPath : $cwd;

        // @codeCoverageIgnoreStart
        if (! is_dir($dirPath)) {
            $dirPath = dirname($dirPath);
        }

        // @codeCoverageIgnoreEnd

        while (true) {
            $configPath = $this->findConfigInDirectory($dirPath);
            if ($configPath !== null) {
                return $configPath;
            }

            $parentDir = dirname($dirPath);
            if ($parentDir === $dirPath) {
                return null;
            }

            $dirPath = $parentDir;
        }
    }

    private function findConfigInDirectory(string $dirPath): ?string
    {
        $configPath = sprintf('%s/%s', $dirPath, 'apidoc.xml');
        if (file_exists($configPath)) {
            return $configPath;
        }

        $distPath = $configPath . '.dist';
        if (file_exists($distPath)) {
            return $distPath;
        }

        return null;
    }

    private function validate(string $xmlContents, string $xmlFullPath, string $xsdPath): void
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            if ($dom->loadXML($xmlContents) && $dom->schemaValidate($xsdPath)) {
                return;
            }

            $this->error($xmlFullPath);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function error(string $xmlFullPath): void
    {
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            if ($error->level === LIBXML_ERR_FATAL || $error->level === LIBXML_ERR_ERROR) {
                $msg = sprintf('%s in %s:%s', substr($error->message, 0, -2), $error->file, $error->line);

                throw new ConfigException($msg);
            }
        }

        // @codeCoverageIgnoreStart
        throw new ConfigException(sprintf('Invalid XML config: %s', $xmlFullPath));
        // @codeCoverageIgnoreEnd
    }
}
