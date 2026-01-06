<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Stringable;

use function sprintf;

/**
 * @psalm-pure
 */

final class SchemaProp implements Stringable
{
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly bool $isOptional,
        public string $description,
        public SchemaConstraints $constraints,
        public string $example
    ) {
    }

    #[\Override]
    public function __toString(): string
    {
        if ($this->name === '_links' || $this->name === '_embedded') {
            return '';
        }

        $required = $this->isOptional ? 'Optional' : 'Required';

        return sprintf('| %s | %s | %s | %s | %s | %s |', $this->name, $this->type, $this->description, $required, (string) $this->constraints, $this->example);
    }
}
