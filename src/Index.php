<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\DocBlock\Tags\Link;
use Stringable;

use function sprintf;

use const PHP_EOL;

final readonly class Index implements Stringable
{
    private string $title;

    private string $description;

    private \BEAR\ApiDoc\TagLinks $links;

    /** @param array<string, string> $paths */
    public function __construct(
        Config $config,
        private array $paths,
        private ModelRepository $objects,
        private string $ext
    ) {
        $this->title = $config->title;
        $this->description = $config->description !== '' && $config->description !== '0' ? $config->description . PHP_EOL . PHP_EOL : '';
        $links = [];
        $hasTermsLink = false;
        foreach ($config->links as $link) {
            $rel = (string) $link['rel'];
            $hasTermsLink = $hasTermsLink || $rel === 'terms';
            $links[] = new Link((string) $link['href'], new Description($rel));
        }

        if (! $hasTermsLink && $this->ext === 'md') {
            $links[] = new Link('terms.md', new Description('terms'));
        }

        $this->links = new TagLinks($links);
    }

    public function __toString(): string
    {
        $paths = '';
        $objects = '';
        foreach ($this->paths as $route => $path) {
            $paths .= sprintf('- [%s](paths/%s.%s)', $route, $path, $this->ext) . PHP_EOL;
        }

        foreach ($this->objects as $objectName => $objectFile) {
            $objects .= sprintf('- [%s](schemas/%s)', $objectName, $objectFile) . PHP_EOL;
        }

        return <<<EOT
# {$this->title}

{$this->description}{$this->links}
## API Endpoints
{$paths}

## Data Models
{$objects}
EOT;
    }
}
