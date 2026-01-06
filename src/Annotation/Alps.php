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
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Alps
{
    public function __construct(
        /** ALPS descriptor ID */
        public readonly string $id,
    ) {
    }
}
