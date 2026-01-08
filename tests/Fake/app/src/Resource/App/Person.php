<?php

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

/**
 * Person resource
 *
 * Manage personal information and identity data.
 */
class Person extends ResourceObject
{
    /**
     * Get person details
     *
     * Retrieve personal information including name and associated organization.
     *
     * @param string $id The unique identifier of the person
     */
    #[Embed(rel: 'org', src: '/org?id={org_id}')]
    #[Link(rel: 'goCard', href: '/card?id={card_id}')]
    #[Link(rel: 'goTickets', href: '/tickets')]
    #[JsonSchema(schema: 'person.json', params: 'person.param.json')]
    public function onGet(string $id = 'koriym'): static
    {
        return $this;
    }

    /**
     * Register a new person
     *
     * Create a new person record in the system.
     *
     * @param string $firstName The person's first name
     * @param string $familyName The person's family/last name
     * @param int    $age       The person's age in years
     */
    #[JsonSchema(params: 'person.param.json')]
    public function onPost(string $firstName, string $familyName = '', int $age = 0): static
    {
        return $this;
    }

    /**
     * Update person information
     *
     * Modify an existing person's details.
     *
     * @param string      $id         The unique identifier of the person
     * @param string|null $firstName  Updated first name
     * @param string|null $familyName Updated family name
     * @param int|null    $age        Updated age
     */
    #[JsonSchema(params: 'person.param.json')]
    public function onPatch(
        string $id,
        ?string $firstName = null,
        ?string $familyName = null,
        ?int $age = null,
    ): static {
        return $this;
    }
}
