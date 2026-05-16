<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

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
}
