<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\InvalidJsonFileException;
use JsonException;

use function array_is_list;
use function file_get_contents;
use function is_array;
use function is_object;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class JsonFile
{
    public function object(string $file): object
    {
        $json = $this->decode($file, false);
        if (! is_object($json)) {
            throw new InvalidJsonFileException(sprintf('JSON root must be an object: %s', $file));
        }

        return $json;
    }

    /** @return array<string, mixed> */
    public function assoc(string $file): array
    {
        $json = $this->decode($file, true);
        if (! is_array($json) || array_is_list($json)) {
            throw new InvalidJsonFileException(sprintf('JSON root must be an object: %s', $file));
        }

        /** @var array<string, mixed> $json */
        return $json;
    }

    private function decode(string $file, bool $assoc): mixed
    {
        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new InvalidJsonFileException(sprintf('Cannot read JSON file: %s', $file));
        }

        try {
            return json_decode($contents, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidJsonFileException(
                sprintf('Invalid JSON in %s: %s', $file, $e->getMessage()),
                0,
                $e,
            );
        }
    }
}
