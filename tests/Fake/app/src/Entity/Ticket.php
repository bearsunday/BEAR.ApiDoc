<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Entity;

use DateTimeImmutable;

class Ticket
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $status,
        public readonly ?string $assignee,
        public readonly DateTimeImmutable $created,
    ) {
    }
}
