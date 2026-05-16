<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;
use Ray\InputQuery\Attribute\Input;

final class JsonSchemaInputFixture extends ResourceObject
{
    #[JsonSchema(params: 'json-schema-input.param.json')]
    public function onPost(#[Input] JsonSchemaInput $input, string $fallback): static
    {
        return $this;
    }
}

final readonly class JsonSchemaInput
{
    public function __construct(
        public mixed $ids,
        public mixed $status = null,
        /** DTO docblock fallback description */
        public mixed $docOnly = null,
    ) {
    }
}
