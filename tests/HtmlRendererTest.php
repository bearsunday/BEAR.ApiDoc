<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function strpos;

final class HtmlRendererTest extends TestCase
{
    public function testRenderOmitsLinksSectionWhenLinksAreEmpty(): void
    {
        $html = (new HtmlRenderer())->render('', '', [], [], []);

        $this->assertStringNotContainsString('<h2>Links</h2>', $html);
    }

    public function testOmitsNavWhenNoLinksAtAll(): void
    {
        $html = (new HtmlRenderer())->render('', '', [], [], [], [], 'alps.html', null, []);

        $this->assertStringNotContainsString('<nav class="doc-nav">', $html);
    }

    public function testRendersAlpsProfileLinksGroupedByTagBeforeEndpoints(): void
    {
        $alpsLinks = [
            ['rel' => 'cache-graph', 'href' => 'qr-graph.html', 'title' => null, 'tag' => null],
            ['rel' => 'service-doc', 'href' => 'index.html', 'title' => 'API Reference', 'tag' => 'reference'],
            ['rel' => 'audit', 'href' => 'audit.html', 'title' => 'Audit Report', 'tag' => 'report'],
        ];
        $docLinks = [['rel' => 'home', 'href' => 'https://example.com']];

        $html = (new HtmlRenderer())->render('T', 'D', [], [], [], $docLinks, 'alps.html', null, $alpsLinks);

        $navPos = strpos($html, '<nav class="doc-nav">');
        $endpointsPos = strpos($html, '<h2>Endpoints</h2>');
        $this->assertIsInt($navPos);
        $this->assertIsInt($endpointsPos);
        $this->assertLessThan($endpointsPos, $navPos);

        $untaggedPos = strpos($html, 'qr-graph.html');
        $referencePos = strpos($html, '<h3 class="doc-nav-tag">reference</h3>');
        $reportPos = strpos($html, '<h3 class="doc-nav-tag">report</h3>');
        $this->assertIsInt($untaggedPos);
        $this->assertIsInt($referencePos);
        $this->assertIsInt($reportPos);
        $this->assertLessThan($referencePos, $untaggedPos);
        $this->assertLessThan($reportPos, $referencePos);

        // rel as the link text when there is no title, without repeating it as a label
        $this->assertStringContainsString('<a href="qr-graph.html">cache-graph</a></li>', $html);
        // title used when present, rel kept as a secondary label
        $this->assertStringContainsString('<a href="index.html">API Reference</a> <span class="doc-link-rel">service-doc</span>', $html);

        // apidoc.xml links share the same nav block
        $this->assertStringContainsString('<strong>home</strong> : <a href="https://example.com">https://example.com</a>', $html);
        $this->assertStringNotContainsString('<h2>Links</h2>', $html);
    }

    public function testNumericStringTagDoesNotCrashRendering(): void
    {
        // PHP coerces numeric string array keys (e.g. "2024") to int; the tag
        // heading must still render as the original string without a TypeError.
        $alpsLinks = [
            ['rel' => 'release-notes', 'href' => 'release-2024.html', 'title' => null, 'tag' => '2024'],
        ];

        $html = (new HtmlRenderer())->render('T', 'D', [], [], [], [], 'alps.html', null, $alpsLinks);

        $this->assertStringContainsString('<h3 class="doc-nav-tag">2024</h3>', $html);
    }

    public function testEscapesAlpsLinkAttributesAndText(): void
    {
        $alpsLinks = [
            ['rel' => 'x"y', 'href' => 'a.html?a=1&b=<2>', 'title' => '<b>T</b>', 'tag' => '<t>'],
        ];

        $html = (new HtmlRenderer())->render('T', 'D', [], [], [], [], 'alps.html', null, $alpsLinks);

        $this->assertStringNotContainsString('<b>T</b>', $html);
        $this->assertStringNotContainsString('<t>', $html);
        $this->assertStringContainsString('&lt;b&gt;T&lt;/b&gt;', $html);
        $this->assertStringContainsString('<h3 class="doc-nav-tag">&lt;t&gt;</h3>', $html);
        $this->assertStringContainsString('href="a.html?a=1&amp;b=&lt;2&gt;"', $html);
        $this->assertStringContainsString('class="doc-link-rel">x&quot;y<', $html);
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
