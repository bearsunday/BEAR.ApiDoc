<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

class SchemaPropTest extends TestCase
{
    /** @var SchemaProp */
    private $prop;

    protected function setUp(): void
    {
        $this->prop = new SchemaProp('name', 'type', true, 'desc', new SchemaConstrains(['minLenght' => 1, 'maxLength' => 10]));
    }

    public function testNewInstance(): void
    {
        $this->assertInstanceOf(SchemaProp::class, $this->prop);
    }

    public function testToString(): void
    {
        $expected = '| name | type | desc | Optional | {"minLenght":1,"maxLength":10} |';
        $this->assertSame($expected, (string) $this->prop);
    }
}
