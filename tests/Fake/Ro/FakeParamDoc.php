<?php

namespace BEAR\ApiDoc\Fake\Ro;

use BEAR\Resource\ResourceObject;

/**
 * Class_Title
 *
 * Class_DesciptionL1
 * Class_DesciptionL2s
 *
 * @link http://www.example.com/
 *
 * @package BEAR\ApiDoc\Fake\Ro
 */
class FakeParamDoc extends ResourceObject
{
    /**
     * @param string $id This is fake id
     */
    public function onGet(string $id): ResourceObject
    {
        unset($id);
        return $this;
    }

    public function onPost(): ResourceObject
    {
        return $this;
    }
}
