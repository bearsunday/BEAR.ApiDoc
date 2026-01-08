<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Fake\Ro\FakeParamDoc;
use FakeVendor\FakeProject\Resource\App\Person;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DocClassTest extends TestCase
{
    public function testToString(): void
    {
        $class = new ReflectionClass(FakeParamDoc::class);
        $view = (new DocClass(
            __DIR__ . '/Fake/var/schema/request',
            __DIR__ . '/Fake/var/schema/response',
            new ModelRepository(),
        ))('title', '/path', $class, new ArrayObject(), 'md');
        $this->assertStringContainsString('/path', $view);
        $this->assertStringContainsString('## GET', $view);
        $this->assertStringContainsString('Request', $view);
    }

    public function testAlpsAttributeWithSemanticDictionary(): void
    {
        /** @var ArrayObject<string, string> $semanticDictionary */
        $semanticDictionary = new ArrayObject([
            'goPerson' => 'Get person details',
            'doCreatePerson' => 'Create a new person',
        ]);

        $class = new ReflectionClass(Person::class);
        $docClass = new DocClass(
            __DIR__ . '/Fake/app/docs/base/schema',
            __DIR__ . '/Fake/app/docs/base/schema',
            new ModelRepository(),
        );
        $view = $docClass('API', '/person', $class, $semanticDictionary, 'md');

        // Verify ALPS section is present
        $this->assertStringContainsString('ALPS', $view);
        $this->assertStringContainsString('goPerson', $view);
        $this->assertStringContainsString('Get person details', $view);
    }

    public function testAlpsAttributeWithoutSemanticDictionary(): void
    {
        $class = new ReflectionClass(Person::class);
        $docClass = new DocClass(
            __DIR__ . '/Fake/app/docs/base/schema',
            __DIR__ . '/Fake/app/docs/base/schema',
            new ModelRepository(),
        );
        /** @var ArrayObject<string, string> $emptyDictionary */
        $emptyDictionary = new ArrayObject();
        $view = $docClass('API', '/person', $class, $emptyDictionary, 'md');

        // ALPS section without semantic info
        $this->assertStringContainsString('ALPS', $view);
        $this->assertStringContainsString('goPerson', $view);
    }

    public function testModelRepository(): void
    {
        $modelRepository = new ModelRepository();
        $class = new ReflectionClass(Person::class);
        $docClass = new DocClass(
            __DIR__ . '/Fake/app/docs/base/schema',
            __DIR__ . '/Fake/app/docs/base/schema',
            $modelRepository,
        );
        /** @var ArrayObject<string, string> $emptyDictionary */
        $emptyDictionary = new ArrayObject();
        $docClass('API', '/person', $class, $emptyDictionary, 'md');

        // Verify model repository is populated with response schemas
        $this->assertNotEmpty($modelRepository->getArrayCopy());
    }

    public function testClassLevelAlpsWithSemanticDictionary(): void
    {
        /** @var ArrayObject<string, string> $semanticDictionary */
        $semanticDictionary = new ArrayObject(['User' => 'User resource description']);

        $class = new ReflectionClass(\FakeVendor\FakeProject\Resource\App\User::class);
        $docClass = new DocClass(
            __DIR__ . '/Fake/app/src/var/json_schema',
            __DIR__ . '/Fake/app/src/var/json_schema',
            new ModelRepository(),
        );
        $view = $docClass('API', '/user', $class, $semanticDictionary, 'md');

        // Verify class-level ALPS section with semantic info
        $this->assertStringContainsString('ALPS', $view);
        $this->assertStringContainsString('User', $view);
        $this->assertStringContainsString('User resource description', $view);
    }
}
