<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function json_encode;

final class JsonSaver
{
    public function __invoke(string $path, string $name, \stdClass $json)
    {
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path)); // @codeCoverageIgnore
        }
        file_put_contents(sprintf('%s/%s.json', $path, $name), json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
