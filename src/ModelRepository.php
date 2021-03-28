<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

class ModelRepository implements VisitorInterface
{
    /** @var array<string, Schema> */
    private $models;

    public function visitSchema(Schema $schema): void
    {
        if ($schema->type === 'object' && $schema->title) {
            $this->models[$schema->title] = $schema;
        }
    }
}
