<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Doc\ThrowingDocBlockFactory;
use FakeVendor\FakeProject\Resource\App\Contact;
use FakeVendor\FakeProject\Resource\App\InputEdgeCases;
use FakeVendor\FakeProject\Resource\App\User;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function array_map;

class InputParamExpanderTest extends TestCase
{
    private InputParamExpander $expander;

    protected function setUp(): void
    {
        $this->expander = new InputParamExpander();
    }

    public function testExpandInputParameter(): void
    {
        $method = new ReflectionMethod(Contact::class, 'onPost');
        $params = ($this->expander)($method);

        // #[Input] ContactInput $contact expands to name, email, age
        // string $subject remains as-is
        $this->assertCount(4, $params);

        $names = array_map(static fn ($p) => $p->getName(), $params);
        $this->assertSame(['name', 'email', 'age', 'subject'], $names);
    }

    public function testExpandedParamTypes(): void
    {
        $method = new ReflectionMethod(Contact::class, 'onPost');
        $params = ($this->expander)($method);

        // name: string (required)
        $this->assertFalse($params[0]->isOptional());
        // email: string (required)
        $this->assertFalse($params[1]->isOptional());
        // age: int (optional, default 0)
        $this->assertTrue($params[2]->isOptional());
        $this->assertSame(0, $params[2]->getDefaultValue());
        // subject: string (required)
        $this->assertFalse($params[3]->isOptional());
    }

    public function testExpandedParamDescriptionFromPromotedPropertyDocblock(): void
    {
        $method = new ReflectionMethod(Contact::class, 'onPost');
        $params = ($this->expander)($method);

        $this->assertInstanceOf(DescribedInputParam::class, $params[0]);
        $this->assertSame('Contact name for display', $params[0]->description);
    }

    public function testExpandedParamWithoutDocblockOmitsDescription(): void
    {
        $method = new ReflectionMethod(Contact::class, 'onPost');
        $params = ($this->expander)($method);

        $this->assertNotInstanceOf(DescribedInputParam::class, $params[2]);
    }

    public function testMalformedDocblockTreatsParamAsUndocumented(): void
    {
        $expander = new InputParamExpander(new ThrowingDocBlockFactory());
        $params = $expander(new ReflectionMethod(Contact::class, 'onPost'));

        $this->assertNotInstanceOf(DescribedInputParam::class, $params[0]);
        $this->assertSame('name', $params[0]->getName());
    }

    public function testNoInputAttribute(): void
    {
        $method = new ReflectionMethod(User::class, 'onGet');
        $params = ($this->expander)($method);

        // Without #[Input], result should match getParameters()
        $expected = array_map(
            static fn ($p) => $p->getName(),
            $method->getParameters(),
        );
        $actual = array_map(static fn ($p) => $p->getName(), $params);
        $this->assertSame($expected, $actual);
    }

    public function testBuiltinTypeWithInputFallsBack(): void
    {
        $method = new ReflectionMethod(InputEdgeCases::class, 'onPost');
        $params = ($this->expander)($method);

        $this->assertCount(1, $params);
        $this->assertSame('builtinType', $params[0]->getName());
    }

    public function testNoConstructorClassFallsBack(): void
    {
        $method = new ReflectionMethod(InputEdgeCases::class, 'onPut');
        $params = ($this->expander)($method);

        $this->assertCount(1, $params);
        $this->assertSame('noCtor', $params[0]->getName());
    }
}
