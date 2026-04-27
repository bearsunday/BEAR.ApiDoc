<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;
use Ray\InputQuery\Attribute\Input;

class DocblockVariants extends ResourceObject
{
    public function onPost(#[Input] DocblockVariantsInput $input): static
    {
        return $this;
    }
}
