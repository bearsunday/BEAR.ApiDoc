<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Stringable;

use function array_map;
use function basename;
use function glob;
use function htmlspecialchars;
use function sort;
use function sprintf;

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
            $list .= sprintf('<li><a href="%s" rel="schema">%s</a></li>', htmlspecialchars($schema), htmlspecialchars($schema)) . "\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>JSON Schemas</title>
<link rel="profile" href="../alps/index-schema.xml">
<style>body{font-family:system-ui,sans-serif;margin:2rem;padding:0}a{color:#0366d6}ul{line-height:1.8}</style>
</head>
<body>
<h1>JSON Schemas</h1>
<ul>
{$list}</ul>
</body>
</html>
HTML;
    }
}
