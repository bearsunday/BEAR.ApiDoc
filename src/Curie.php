<?php
namespace BEAR\ApiDoc;

final class Curie
{
    public $nameRel;
    public $rel;
    public $href;
    public $title;
    public $docUri;

    public function __construct(string $nameRel, array $curie, Curies $curies)
    {
        $this->nameRel = $nameRel;
        $this->href = $curie['href'];
        $this->title = $curie['title'] ?? '';
        $this->rel = str_replace($curies->name . ':', '', $nameRel);
        $this->docUri = uri_template($curies->href, ['rel' => $this->rel]);
    }
}
