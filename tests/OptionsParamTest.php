<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\AppMeta\Meta;
use BEAR\Resource\ResourceInterface;
use FakeVendor\FakeProject\Resource\App\Contact;
use FakeVendor\FakeProject\Resource\App\Person;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use ReflectionMethod;

use function array_map;
use function in_array;

class OptionsParamTest extends TestCase
{
    private OptionsParam $optionsParam;

    protected function setUp(): void
    {
        $meta = new Meta('FakeVendor\FakeProject');
        $appMetaModule = new AppMetaModule($meta);
        $appModule = new \FakeVendor\FakeProject\Module\AppModule($meta, $appMetaModule);
        $appModule->override($appMetaModule);
        $injector = new Injector($appModule);

        /** @var ResourceInterface $resource */
        $resource = $injector->getInstance(ResourceInterface::class);
        $this->optionsParam = new OptionsParam($resource, 'app');
    }

    public function testOptionsSuccessPath(): void
    {
        $params = ($this->optionsParam)('/contact', 'onPost');
        $names = array_map(static fn (ParamMeta $p) => $p->name, $params);

        // OPTIONS succeeds and returns parameters (expanded or raw depending on bear/resource version)
        $this->assertNotEmpty($params);
        $this->assertContains('subject', $names);
    }

    public function testInputExpansionViaOptions(): void
    {
        $params = ($this->optionsParam)('/contact', 'onPost');
        $names = array_map(static fn (ParamMeta $p) => $p->name, $params);

        if (in_array('contact', $names, true)) {
            $this->markTestSkipped('#[Input] expansion not supported in this bear/resource version');
        }

        // OPTIONS success expands #[Input] ContactInput into individual properties
        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
        $this->assertContains('subject', $names);
        $this->assertNotContains('contact', $names);
    }

    public function testInputExpansionTypes(): void
    {
        $params = ($this->optionsParam)('/contact', 'onPost');
        $names = array_map(static fn (ParamMeta $p) => $p->name, $params);

        if (in_array('contact', $names, true)) {
            $this->markTestSkipped('#[Input] expansion not supported in this bear/resource version');
        }

        $indexed = [];
        foreach ($params as $param) {
            $indexed[$param->name] = $param;
        }

        $this->assertSame('string', $indexed['name']->type);
        $this->assertSame('string', $indexed['email']->type);
        $this->assertSame('integer', $indexed['age']->type);
        $this->assertSame('string', $indexed['subject']->type);
    }

    public function testInputExpansionOptionality(): void
    {
        $params = ($this->optionsParam)('/contact', 'onPost');
        $names = array_map(static fn (ParamMeta $p) => $p->name, $params);

        if (in_array('contact', $names, true)) {
            $this->markTestSkipped('#[Input] expansion not supported in this bear/resource version');
        }

        $indexed = [];
        foreach ($params as $param) {
            $indexed[$param->name] = $param;
        }

        // name, email, subject are required; age is optional (has default 0)
        $this->assertFalse($indexed['name']->isOptional);
        $this->assertFalse($indexed['email']->isOptional);
        $this->assertTrue($indexed['age']->isOptional);
        $this->assertFalse($indexed['subject']->isOptional);
    }

    public function testInputExpansionDefault(): void
    {
        $params = ($this->optionsParam)('/contact', 'onPost');
        $names = array_map(static fn (ParamMeta $p) => $p->name, $params);

        if (in_array('contact', $names, true)) {
            $this->markTestSkipped('#[Input] expansion not supported in this bear/resource version');
        }

        $indexed = [];
        foreach ($params as $param) {
            $indexed[$param->name] = $param;
        }

        $this->assertSame('0', $indexed['age']->default);
        $this->assertSame('', $indexed['name']->default);
    }

    public function testFallbackToReflection(): void
    {
        $method = new ReflectionMethod(Person::class, 'onGet');
        $params = ($this->optionsParam)('/nonexistent-resource-xyz', 'onGet', $method);
        $names = array_map(static fn (ParamMeta $p) => $p->name, $params);

        // Falls back to reflection and extracts parameter from Person::onGet
        $this->assertContains('id', $names);
    }

    public function testFallbackToReflectionWithInput(): void
    {
        $method = new ReflectionMethod(Contact::class, 'onPost');
        $params = ($this->optionsParam)('/nonexistent-resource-xyz', 'onPost', $method);
        $names = array_map(static fn (ParamMeta $p) => $p->name, $params);

        // Reflection fallback does NOT expand #[Input], returns raw parameter names
        $this->assertContains('contact', $names);
        $this->assertContains('subject', $names);
        $this->assertNotContains('name', $names);
    }

    public function testFallbackWithoutMethod(): void
    {
        $params = ($this->optionsParam)('/nonexistent-resource-xyz', 'onGet');

        // No ReflectionMethod provided, returns empty
        $this->assertSame([], $params);
    }
}
