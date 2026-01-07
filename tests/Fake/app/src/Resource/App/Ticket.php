<?php

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

/**
 * Ticket resource
 *
 * Manage support tickets for customer inquiries and issue tracking.
 */
class Ticket extends ResourceObject
{
    /**
     * Get a ticket
     *
     * Retrieve detailed information about a specific support ticket.
     *
     * @param string $id The unique identifier for a ticket
     */
    #[JsonSchema(key: 'ticket', schema: 'ticket.json', params: 'ticket.param.json')]
    #[Link(rel: 'goAssignee', href: '/user{?id}')]
    public function onGet(string $id): static
    {
        unset($id);

        return $this;
    }

    /**
     * Create a new ticket
     *
     * Create a new support ticket with title and description.
     *
     * @param string $title       The title summarizing the issue
     * @param string $description Detailed description of the problem
     * @param string $assignee    Username of the person responsible
     */
    #[JsonSchema(params: 'ticket.param.json')]
    public function onPost(
        string $title,
        string $description = 'default desc',
        string $assignee = 'default assignee',
    ): static {
        return $this;
    }

    /**
     * Update a ticket
     *
     * Modify an existing ticket's information.
     *
     * @param string      $id          The unique identifier for a ticket
     * @param string|null $title       Updated title of the ticket
     * @param string|null $status      New status (open, in_progress, resolved, closed)
     * @param string|null $assignee    New assignee username
     * @param string|null $description Updated description
     */
    #[JsonSchema(params: 'ticket.param.json')]
    public function onPut(
        string $id,
        ?string $title = null,
        ?string $status = null,
        ?string $assignee = null,
        ?string $description = null,
    ): static {
        return $this;
    }

    /**
     * Delete a ticket
     *
     * Permanently remove a ticket from the system.
     *
     * @param string $id The unique identifier of the ticket to delete
     */
    #[JsonSchema(params: 'ticket.param.json')]
    public function onDelete(string $id): static
    {
        return $this;
    }
}
