<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Query;

use FakeVendor\FakeProject\Entity\User;

interface UserQueryInterface
{
    public function getUser(string $id): User;

    /** @return array<User> */
    public function getUsers(int $limit = 10): array;
}
