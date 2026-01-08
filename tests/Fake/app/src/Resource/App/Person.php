<?php

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\ApiDoc\Annotation\Alps;
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
     * Get person
     *
     * @param string $id The unique identifier of the person
     */
    #[Alps('goPerson')]
    #[Embed(rel: 'org', src: '/org?id={org_id}')]
    #[Link(rel: 'goCard', href: '/card?id={card_id}')]
    #[Link(rel: 'goTickets', href: '/tickets')]
    #[Link(rel: 'doDelete', href: '/person?id={id}')]
    #[JsonSchema(schema: 'person.json', params: 'person.param.json')]
    public function onGet(string $id = 'koriym'): static
    {
        return $this;
    }

    /**
     * Register member
     *
     * @param string $firstName The person's first name
     * @param string $familyName The person's family/last name
     * @param int    $age       The person's age in years
     */
    #[Alps('doCreatePerson')]
    #[JsonSchema(params: 'person.param.json')]
    public function onPost(string $firstName, string $familyName = '', int $age = 0): static
    {
        return $this;
    }

    /**
     * Update profile
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
