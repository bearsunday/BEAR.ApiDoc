<?php

namespace BEAR\ApiDoc;

final class Curies
{
    public $href;
    public $name;
    public $templated;

    public function __construct(array $curie)
    {
        $this->href = $curie['href'];
        $this->name = $curie['name'];
        $this->templated = $curie['templated'];
    }
}
