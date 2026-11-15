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
}
