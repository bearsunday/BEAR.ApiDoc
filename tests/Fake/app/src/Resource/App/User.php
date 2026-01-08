<?php

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

/**
 * User resource
 *
 * Manage user accounts and profile information.
 */
#[Alps('User')]
class User extends ResourceObject
{
    /**
     * Get user profile
     *
     * Retrieve detailed profile information for a specific user.
     *
     * @param string $id      The unique identifier of the user
     * @param string $options Display options (guest, full, minimal)
     */
    #[Alps('getUser')]
    #[JsonSchema(schema: 'user.json', params: 'user.param.json')]
    #[Link(rel: 'goPerson', href: '/person', method: 'get')]
    #[Link(rel: 'goCalendar', href: '/calendar', method: 'get')]
    #[Embed(rel: 'ticket', src: '/ticket/{id}')]
    public function onGet(string $id, string $options = 'guest'): static
    {
        return $this;
    }

    /**
     * Create a new user
     *
     * Register a new user account in the system.
     *
     * @param string $name  The display name of the user
     * @param int    $age   The age of the user in years
     * @param string $email The email address for the user account
     */
    #[Alps('createUser')]
    #[JsonSchema(params: 'user.param.json')]
    public function onPost(string $name, int $age, string $email = ''): static
    {
        return $this;
    }

    /**
     * Update user profile
     *
     * Modify existing user account information.
     *
     * @param string      $id    The unique identifier of the user
     * @param string|null $name  Updated display name
     * @param int|null    $age   Updated age
     * @param string|null $email Updated email address
     * @param bool|null   $enabled Whether the account is active
     */
    #[Alps('updateUser')]
    #[JsonSchema(params: 'user.param.json')]
    public function onPut(
        string $id,
        ?string $name = null,
        ?int $age = null,
        ?string $email = null,
        ?bool $enabled = null,
    ): static {
        return $this;
    }

    /**
     * Delete user account
     *
     * Permanently delete a user account and associated data.
     *
     * @param string $id The unique identifier of the user to delete
     */
    #[Alps('deleteUser')]
    #[JsonSchema(params: 'user.param.json')]
    public function onDelete(string $id): static
    {
        return $this;
    }
}
