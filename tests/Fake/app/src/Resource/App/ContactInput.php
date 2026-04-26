<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

final readonly class ContactInput
{
    public function __construct(
        /** Contact name for display */
        public string $name,
        /** Contact email address */
        public string $email,
        public int $age = 0,
    ) {
    }
}
