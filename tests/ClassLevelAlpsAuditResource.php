<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;

/** Class-level ALPS resource used to exercise audit ALPS coverage. */
#[Alps('classLevelDocumented')]
final class ClassLevelAlpsAuditResource
{
    /**
     * Gets a class-level documented resource.
     */
    #[JsonSchema(schema: 'documented.json')]
    public function onGet(string $id): void
    {
    }
}
