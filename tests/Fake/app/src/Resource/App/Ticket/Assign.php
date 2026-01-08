<?php

namespace FakeVendor\FakeProject\Resource\App\Ticket;

use BEAR\Resource\ResourceObject;

/**
 * Assign ticket (Domain command example)
 *
 * This resource demonstrates domain command style.
 * Compare with /ticket for CRUD-style operations.
 */
class Assign extends ResourceObject
{
    /**
     * Assign ticket
     *
     * CQRS domain command. Compare with CRUD operations at /ticket.
     *
     * @param string $id       Ticket ID
     * @param string $assignee Username of the assignee
     */
    public function onPut(string $id, string $assignee): static
    {
        return $this;
    }
}
