<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

final readonly class DocblockVariantsInput
{
    public function __construct(
        /**
         * Short summary line.
         *
         * Longer description on a second paragraph.
         */
        public string $bothSummaryAndDescription,
        /**
         * @internal tag-only docblock has neither summary nor description
         */
        public string $tagOnly,
        string $nonPromoted,
    ) {
    }
}
