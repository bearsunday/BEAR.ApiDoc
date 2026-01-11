<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Entity;

use DateTimeImmutable;

class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly int $age,
        public readonly DateTimeImmutable $created,
    ) {
    }
}
