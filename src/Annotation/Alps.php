<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Annotation;

use Attribute;

use function assert;

/**
 * Maps REST resource/method to ALPS semantic descriptor
 *
 * On class: Maps to ALPS Taxonomy (state)
 * On method: Maps to ALPS Choreography (transition)
 *
 * @see https://alps-io.github.io/spec/
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Alps
{
    public function __construct(
        /** ALPS descriptor ID */
        public string $id,
    ) {
        assert($id !== '', 'ALPS descriptor ID must not be empty');
    }
}
