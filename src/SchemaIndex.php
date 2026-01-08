<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Stringable;

use function array_map;
use function basename;
use function glob;
use function htmlspecialchars;
use function pathinfo;
use function sort;
use function sprintf;

use const PATHINFO_FILENAME;

final readonly class SchemaIndex implements Stringable
{
    /** @var list<string> */
    private array $schemas;

    public function __construct(string $schemaDir)
    {
        $files = glob($schemaDir . '/*.json');
        $files = $files !== false ? $files : [];
        $schemas = array_map(static fn (string $file): string => basename($file), $files);
        sort($schemas);
        $this->schemas = $schemas;
    }

    #[\Override]
    public function __toString(): string
    {
        $list = '';
        foreach ($this->schemas as $schema) {
            $name = pathinfo($schema, PATHINFO_FILENAME);
            $list .= sprintf('<li class="schema"><a href="%s" class="goSchema">%s</a></li>', htmlspecialchars($schema), htmlspecialchars($name)) . "\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>JSON Schemas</title>
<link rel="profile" href="http://alps.io/spec/index.html">
<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:2rem auto;padding:0 1rem}a{color:#0366d6}ul{line-height:1.8}</style>
</head>
<body>
<h1 class="schemaIndex">JSON Schemas</h1>
<ul class="schemaList">
{$list}</ul>
</body>
</html>
HTML;
    }
}
