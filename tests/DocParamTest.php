<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Fake\Ro\FakeIndex;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;

class DocParamTest extends TestCase
{
    public function testFromParameter(): void
    {
        $param = new ReflectionParameter([FakeIndex::class, 'onGet'], 'id');
        $docParam = new DocParam($param, new TagParam('', ''), null, new ArrayObject());
        $this->assertInstanceOf(DocParam::class, $docParam);
    }
}
