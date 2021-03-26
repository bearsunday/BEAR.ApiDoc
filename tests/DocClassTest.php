<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Fake\Ro\FakeParamDoc;
use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DocClassTest extends TestCase
{
    public function testToString(): void
    {
        $docClass = new DocClass(new AnnotationReader(), __DIR__ . '/Fake/var/schema/request', __DIR__ . '/Fake/var/schema/response', new ArrayObject());
        $view = $docClass('', new ReflectionClass(FakeParamDoc::class));
        $expected = <<<EOT
### Request
| Name  | Type  | Description | Default | Example |
|-------|-------|-------------|---------|---------| 
| id | string | This is fake id |  |  |
EOT;
        $this->assertStringContainsString($expected, $view);
        $this->assertStringContainsString('## GET', $view);
        $this->assertStringContainsString('### Request', $view);
    }
}
