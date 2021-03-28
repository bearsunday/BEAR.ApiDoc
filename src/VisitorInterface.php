<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

interface VisitorInterface
{
    public function visitSchema(Schema $schema): void;
}
