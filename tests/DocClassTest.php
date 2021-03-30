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
        $class = new ReflectionClass(FakeParamDoc::class);
        $view = (new DocClass(
            new AnnotationReader(),
            __DIR__ . '/Fake/var/schema/request',
            __DIR__ . '/Fake/var/schema/response',
            new ArrayObject(),
        ))('/path', $class, new ArrayObject());
        $this->assertStringContainsString('/path', $view);
        $this->assertStringContainsString('## GET', $view);
        $this->assertStringContainsString('### Request', $view);
    }
}
