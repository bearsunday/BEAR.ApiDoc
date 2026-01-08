<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Annotation;

use Attribute;

/**
 * Maps REST resource/method to ALPS semantic descriptor
 *
 * On class: Maps to ALPS Taxonomy (state)
 * On method: Maps to ALPS Choreography (transition)
 *
 * @see https://alps-io.github.io/spec/
 */
/** @psalm-import-type AlpsDescriptorId from \BEAR\ApiDoc\Types */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Alps
{
    /** @param AlpsDescriptorId $id */
    public function __construct(
        public string $id,
    ) {
    }
}
