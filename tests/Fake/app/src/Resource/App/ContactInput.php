<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

final readonly class ContactInput
{
    public function __construct(
        public string $name,
        public string $email,
        public int $age = 0,
    ) {
    }
}
