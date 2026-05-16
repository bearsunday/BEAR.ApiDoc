<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use function count;
use function explode;
use function str_repeat;
use function str_starts_with;
use function substr;
use function trim;

final readonly class FakeDataExample
{
    public function __construct(
        public string $componentName,
        public string $summary,
        public string $externalValue,
    ) {
    }

    /** @return array{summary: string, externalValue: string} */
    public function toOpenApiExampleObject(): array
    {
        return [
            'summary' => $this->summary,
            'externalValue' => $this->externalValue,
        ];
    }

    /** @return array{fake: array{'$ref': string}} */
    public function toMediaTypeExamples(): array
    {
        return [
            'fake' => [
                '$ref' => '#/components/examples/' . $this->componentName,
            ],
        ];
    }

    /**
     * Return externalValue as a path relative to docDir/$subdir.
     *
     * externalValue is stored relative to docDir; outputs nested under
     * a subdirectory (e.g. Markdown files in docDir/paths/) need to walk
     * back up one level per segment.
     */
    public function externalValueRelativeTo(string $subdir): string
    {
        $normalized = trim($subdir, '/');
        if ($normalized === '' || $normalized === '.') {
            return $this->externalValue;
        }

        $depth = count(explode('/', $normalized));
        $prefix = str_repeat('../', $depth);
        $path = str_starts_with($this->externalValue, './')
            ? substr($this->externalValue, 2)
            : $this->externalValue;

        return $prefix . $path;
    }
}
