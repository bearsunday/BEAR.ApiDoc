<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\AlpsFileNotFoundException;
use BEAR\ApiDoc\Exception\InvalidAppNamespaceException;
use BEAR\AppMeta\Meta;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_get_contents;
use function unlink;

class ApiDocTest extends TestCase
{
    public function testHtmlOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.html.xml');

        $this->assertStringContainsString('ApiDoc generated', $result);
        $this->assertStringContainsString('terms.html', $result);
        $this->assertFileExists(__DIR__ . '/../docs/index.html');
        $this->assertFileExists(__DIR__ . '/../docs/terms.html');

        $html = file_get_contents(__DIR__ . '/../docs/index.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('<strong>terms</strong> : <a href="terms.html">terms.html</a>', $html);
    }

    public function testMdOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.md.xml');

        $this->assertStringContainsString('ApiDoc generated', $result);
        $this->assertStringContainsString('terms.md', $result);
        $this->assertFileExists(__DIR__ . '/docs/md/index.md');
        $this->assertFileExists(__DIR__ . '/docs/md/terms.md');

        $markdown = file_get_contents(__DIR__ . '/docs/md/index.md');
        $this->assertIsString($markdown);
        $this->assertStringContainsString(' * terms [terms.md](terms.md)', $markdown);
    }

    public function testAlpsHtmlOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.alps.xml');

        $this->assertStringContainsString('ApiDoc generated', $result);
        $this->assertFileExists(__DIR__ . '/docs/html/index.html');

        // Verify ALPS integration
        $html = file_get_contents(__DIR__ . '/docs/html/index.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('Person', $html);
    }

    public function testAlpsDescriptionFallback(): void
    {
        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.alps.xml');

        $html = file_get_contents(__DIR__ . '/docs/html/index.html');
        $this->assertIsString($html);

        // Verify ALPS semantic dictionary is used as fallback for empty descriptions
        // AlpsFallback resource has parameters without PHPDoc descriptions
        // ALPS profile defines semantic information that should be used as fallback

        // profile.json defines: firstName with title - shown in description column
        $this->assertStringContainsString('<td class="param-desc">First Name by ALPS</td>', $html);

        // profile.json defines: familyName with def - shown as link in description column
        $this->assertStringContainsString('schema.org/familyName', $html);

        // profile.json defines: age with doc - shown in description column
        $this->assertStringContainsString('Age in years which must be equal to or greater than zero.', $html);

        // profile.json defines: foo without title/doc/def - description should be empty
        $this->assertMatchesRegularExpression('/<td><span class="param">foo<\/span>.*<td class="param-desc"><\/td>/s', $html);
    }

    public function testOpenApiOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.openapi.xml');

        $this->assertStringContainsString('openapi.json', $result);
        $this->assertFileExists(__DIR__ . '/docs/openapi/openapi.json');
    }

    public function testInlineCss(): void
    {
        $apiDoc = new ApiDoc(inlineCss: true);
        $apiDoc(__DIR__ . '/apidoc.html.xml');

        $html = file_get_contents(__DIR__ . '/../docs/index.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('<style', $html);
    }

    public function testAlpsFileNotFound(): void
    {
        $this->expectException(AlpsFileNotFoundException::class);

        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.alps-invalid.xml');
    }

    public function testHtmlOutputWithoutLinks(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.html-nolinks.xml');

        $this->assertStringContainsString('ApiDoc generated', $result);
        $this->assertFileExists(__DIR__ . '/docs/html-nolinks/index.html');
        $this->assertFileExists(__DIR__ . '/docs/html-nolinks/terms.html');

        $html = file_get_contents(__DIR__ . '/docs/html-nolinks/index.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('<h2>Links</h2>', $html);
        $this->assertStringContainsString('<strong>terms</strong> : <a href="terms.html">terms.html</a>', $html);
    }

    public function testInvalidAppNamespace(): void
    {
        $this->expectException(InvalidAppNamespaceException::class);

        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.invalid-app.xml');
    }

    public function testLlmsOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.llms.xml');

        $this->assertStringContainsString('llms.txt', $result);
        $this->assertFileExists(__DIR__ . '/docs/llms.txt');

        $content = file_get_contents(__DIR__ . '/docs/llms.txt');
        $this->assertIsString($content);
        $this->assertStringContainsString('# FakeVendor\\FakeProject', $content);
        $this->assertStringContainsString('## Routes', $content);
        $this->assertStringContainsString('## ResourceObjects', $content);
    }

    public function testAuditOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.audit.xml');

        $this->assertStringContainsString('audit.md', $result);
        $this->assertFileExists(__DIR__ . '/docs/audit/audit.md');

        $content = file_get_contents(__DIR__ . '/docs/audit/audit.md');
        $this->assertIsString($content);
        $this->assertStringContainsString('# API Documentation Audit', $content);
        $this->assertStringContainsString('## Findings', $content);

        // The audit format also emits the HTML report, bound to its own profile.
        $this->assertStringContainsString('audit.html', $result);
        $this->assertFileExists(__DIR__ . '/docs/audit/audit.html');

        $html = file_get_contents(__DIR__ . '/docs/audit/audit.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('<link rel="profile" href="https://bearsunday.github.io/BEAR.ApiDoc/alps/audit.xml">', $html);
        $this->assertStringContainsString('<main class="apiDocumentationAudit">', $html);
    }

    public function testTermsOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.terms.xml');

        $this->assertStringContainsString('terms.md', $result);
        $this->assertFileExists(__DIR__ . '/docs/terms/terms.md');

        $content = file_get_contents(__DIR__ . '/docs/terms/terms.md');
        $this->assertIsString($content);
        $this->assertStringContainsString('# Term Usage Index', $content);
        $this->assertStringContainsString('Lexical ALPS coverage', $content);
    }

    public function testBindingsOutput(): void
    {
        $meta = new Meta('FakeVendor\FakeProject', 'app');
        $bindingsFile = $meta->tmpDir . '/bindings.md';
        $signatureFile = $bindingsFile . '.signature';
        foreach ([$bindingsFile, $signatureFile] as $artifact) {
            if (file_exists($artifact)) {
                unlink($artifact);
            }
        }

        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.bindings.xml');

        $this->assertStringContainsString('bindings.html', $result);
        $this->assertFileExists(__DIR__ . '/docs/bindings/bindings.html');

        $html = file_get_contents(__DIR__ . '/docs/bindings/bindings.html');
        $this->assertIsString($html);
        // Bindings page + source map from walked-up composer.lock
        $this->assertStringContainsString('id="src"', $html);
        $this->assertStringContainsString('id="srcmap"', $html);
        $this->assertStringContainsString(
            'FakeVendor\FakeProject\Module\GraphRootInterface- =&gt; (dependency) FakeVendor\FakeProject\Module\GraphRoot',
            $html,
        );
        $this->assertStringNotContainsString('FakeVendor\FakeProject\Module\GraphDependency- =&gt;', $html);
        $this->assertStringNotContainsString('Ray\Di\ProviderSetModule', $html);
        $this->assertFileDoesNotExist($bindingsFile);
        $this->assertFileDoesNotExist($signatureFile);
        // DOT artifact; browser renders (no Graphviz at generation time)
        $this->assertFileExists(__DIR__ . '/docs/bindings/object-graph.dot');
        $dot = file_get_contents(__DIR__ . '/docs/bindings/object-graph.dot');
        $this->assertIsString($dot);
        $this->assertStringContainsString('class_FakeVendor_FakeProject_Module_GraphDependency', $dot);
        $this->assertStringContainsString('id="object-graph-dot"', $html);
        $this->assertStringContainsString('label=\\u003C\\u003Ctable', $html);
        $this->assertStringNotContainsString('</script><script', $html);
        // Shared assets stay on their CDNs; only DOT data and semantic controls are embedded.
        $this->assertStringContainsString(BindingsHtmlRenderer::CSS_URL, $html);
        $this->assertStringContainsString(BindingsHtmlRenderer::JS_URL, $html);
        $this->assertStringContainsString('docs/assets/bindings-object-graph.css', $html);
        $this->assertStringContainsString('docs/assets/bindings-object-graph.js', $html);
        $this->assertStringContainsString('@viz-js/viz@3.28.0/dist/viz-global.js', $html);
        $this->assertStringContainsString('<form class="object-graph-search" role="search">', $html);
        $this->assertStringContainsString('type="search" id="object-graph-search"', $html);
        $this->assertStringContainsString('id="object-graph-search-count"', $html);
        $this->assertStringContainsString('<div class="object-graph" id="object-graph-mount" tabindex="0"', $html);
        $this->assertStringContainsString('id="object-graph-zoom-in" aria-label="Zoom in"', $html);
        $this->assertStringContainsString('id="object-graph-zoom-out" aria-label="Zoom out"', $html);
        $this->assertStringNotContainsString('<a class="object-graph"', $html);
        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringNotContainsString('href="bindings.css"', $html);
        $this->assertStringNotContainsString('src="bindings.js"', $html);
    }

    public function testMultipleFormatsOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.multi.xml');

        $this->assertStringContainsString('index.html', $result);
        $this->assertStringContainsString('terms.html', $result);
        $this->assertStringContainsString('llms.txt', $result);
        $this->assertFileExists(__DIR__ . '/docs/multi/index.html');
        $this->assertFileExists(__DIR__ . '/docs/multi/terms.html');
        $this->assertFileExists(__DIR__ . '/docs/multi/llms.txt');
    }

    public function testHtmlOutputWithFakeExamples(): void
    {
        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.html.fake.xml');

        $html = file_get_contents(__DIR__ . '/docs/html-fake/index.html');
        $this->assertIsString($html);

        // Response examples are sourced directly from fake-data files
        $this->assertStringContainsString('class="method-examples"', $html);
        $this->assertStringContainsString('../../Fake/app/src/var/fake/ticket.json', $html);
        $this->assertStringContainsString('../../Fake/app/src/var/fake/tickets.json', $html);
        $this->assertStringContainsString('../../Fake/app/src/var/fake/person.json', $html);

        // Request bodies project from response payloads into docDir/examples
        $this->assertStringContainsString('./examples/ticket.param.json', $html);
        $this->assertFileExists(__DIR__ . '/docs/html-fake/examples/ticket.param.json');
    }

    public function testMdOutputWithFakeExamples(): void
    {
        $apiDoc = new ApiDoc();
        $apiDoc(__DIR__ . '/apidoc.md.fake.xml');

        $ticketMd = file_get_contents(__DIR__ . '/docs/md-fake/paths/ticket.md');
        $this->assertIsString($ticketMd);
        $this->assertStringContainsString('#### External Example', $ticketMd);
        // GET response references the fake-data source file (no derivation needed)
        $this->assertStringContainsString('[ticket.json](../../../Fake/app/src/var/fake/ticket.json)', $ticketMd);
        // POST/PUT requests project into docDir/examples
        $this->assertStringContainsString('[ticket.param.json](../examples/ticket.param.json)', $ticketMd);

        $ticketsMd = file_get_contents(__DIR__ . '/docs/md-fake/paths/tickets.md');
        $this->assertIsString($ticketsMd);
        $this->assertStringContainsString('[tickets.json](../../../Fake/app/src/var/fake/tickets.json)', $ticketsMd);

        $this->assertFileExists(__DIR__ . '/docs/md-fake/examples/ticket.param.json');
    }
}
