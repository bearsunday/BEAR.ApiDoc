<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;
use Ray\InputQuery\Attribute\Input;

class ContactWithDescriptions extends ResourceObject
{
    #[JsonSchema(params: 'contact.param.json')]
    public function onPost(#[Input] ContactInput $contact, string $subject): static
    {
        return $this;
    }
}
