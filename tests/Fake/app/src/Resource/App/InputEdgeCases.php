<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;
use Ray\InputQuery\Attribute\Input;

class InputEdgeCases extends ResourceObject
{
    public function onPost(#[Input] string $builtinType): static
    {
        return $this;
    }

    public function onPut(#[Input] NoConstructorInput $noCtor): static
    {
        return $this;
    }
}
