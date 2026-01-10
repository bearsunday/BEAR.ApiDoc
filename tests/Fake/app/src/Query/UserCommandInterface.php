<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Query;

interface UserCommandInterface
{
    public function create(string $id, string $name, int $age, string $email): void;

    public function update(string $id, string $name, int $age): void;

    public function delete(string $id): void;
}
