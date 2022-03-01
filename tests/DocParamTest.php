<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Fake\Ro\FakeIndex;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use SplFileInfo;

class DocParamTest extends TestCase
{
    public function testFromParameter(): void
    {
        $param = new ReflectionParameter([FakeIndex::class, 'onGet'], 'id');
        $docParam = new DocParam($param, new TagParam('', ''), null, new ArrayObject());
        $this->assertInstanceOf(DocParam::class, $docParam);
        $this->assertSame('| id | string |  |  | Required |  |  ', (string) $docParam);
    }

    public function testFromProp(): void
    {
        $prop = new SchemaProp(
            'name',
            'string',
            true,
            'description from prop',
            new SchemaConstraints(new ArrayObject([]), new SplFileInfo('')),
            'example'
        );
        $param = new ReflectionParameter([FakeIndex::class, 'onGet'], 'id');
        $docParam = new DocParam($param, new TagParam('', ''), $prop, new ArrayObject());
        $this->assertInstanceOf(DocParam::class, $docParam);
        $this->assertSame('| id | string | description from prop |  | Required |  | example ', (string) $docParam);
    }
}
