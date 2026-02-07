<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\ResourceObject;
use Ray\InputQuery\Attribute\Input;

class Contact extends ResourceObject
{
    public function onPost(#[Input] ContactInput $contact, string $subject): static
    {
        return $this;
    }
}
