<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\Resource\Annotation\JsonSchema;

/** Resource without ALPS used to exercise disabled ALPS audit paths. */
final class NoAlpsAuditResource
{
    /**
     * Gets a documented resource without ALPS.
     */
    #[JsonSchema(schema: 'documented.json')]
    public function onGet(string $id): void
    {
    }
}
