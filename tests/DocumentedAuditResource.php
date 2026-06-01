<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;

/** Documented resource used to exercise the no-findings audit path. */
final class DocumentedAuditResource
{
    /**
     * Updates a documented resource.
     */
    #[Alps('documented')]
    #[JsonSchema(schema: 'documented.json', params: 'documented.param.json')]
    public function onPut(string $id, string $name): void
    {
    }
}
