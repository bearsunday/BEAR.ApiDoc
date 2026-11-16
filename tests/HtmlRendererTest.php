<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class HtmlRendererTest extends TestCase
{
    public function testRenderOmitsLinksSectionWhenLinksAreEmpty(): void
    {
        $html = (new HtmlRenderer())->render('', '', [], [], []);

        $this->assertStringNotContainsString('<h2>Links</h2>', $html);
    }

    public function testUnknownExampleLinkKindsAreIgnored(): void
    {
        $method = new ReflectionMethod(HtmlRenderer::class, 'renderExampleLinks');

        $this->assertSame('', $method->invoke(
            new HtmlRenderer(),
            ['other' => ['href' => 'examples/article.json', 'label' => 'Other']],
        ));
    }

    public function testPropertyWithoutTypeShowsItsKeywordsAsWritten(): void
    {
        $property = [
            'name' => 'nameColor',
            'type' => 'anyOf',
            'description' => '',
            'example' => null,
            'format' => null,
            'constraints' => [
                'anyOf' => [['$ref' => '#/definitions/color'], ['type' => 'null']],
                'oneOf' => [['const' => 'a']],
                'allOf' => [['maxLength' => 5]],
            ],
            'ref' => null,
        ];
        $anything = ['name' => 'anything', 'type' => '', 'description' => 'any value', 'example' => null, 'format' => null, 'constraints' => [], 'ref' => null];
        $html = (new HtmlRenderer())->render('', '', [], ['Foo' => ['name' => 'Foo', 'properties' => [$property, $anything], 'arrayItemType' => null, 'schemaFile' => null]], []);

        $this->assertStringContainsString('<span class="badge keyword">anyOf</span>', $html);
        $this->assertStringNotContainsString('class="badge type-"', $html);
        $this->assertStringContainsString('<span class="badge constraint">anyOf: <code>[{&quot;$ref&quot;:&quot;#/definitions/color&quot;},{&quot;type&quot;:&quot;null&quot;}]</code></span>', $html);
        $this->assertStringContainsString('<span class="badge constraint">oneOf: <code>[{&quot;const&quot;:&quot;a&quot;}]</code></span>', $html);
        $this->assertStringContainsString('<span class="badge constraint">allOf: <code>[{&quot;maxLength&quot;:5}]</code></span>', $html);
    }
}
