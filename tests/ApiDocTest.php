<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\AlpsFileNotFoundException;
use BEAR\ApiDoc\Exception\InvalidAppNamespaceException;
use PHPUnit\Framework\TestCase;

use function file_get_contents;

class ApiDocTest extends TestCase
{
    public function testHtmlOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.html.xml');

        $this->assertStringContainsString('ApiDoc generated', $result);
        $this->assertFileExists(__DIR__ . '/../docs/index.html');
    }

    public function testMdOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.md.xml');

        $this->assertStringContainsString('ApiDoc generated', $result);
        $this->assertFileExists(__DIR__ . '/docs/md/index.md');
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

        // Verify no Links section
        $html = file_get_contents(__DIR__ . '/docs/html-nolinks/index.html');
        $this->assertIsString($html);
        $this->assertStringNotContainsString('<h2>Links</h2>', $html);
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

    public function testMultipleFormatsOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.multi.xml');

        $this->assertStringContainsString('index.html', $result);
        $this->assertStringContainsString('llms.txt', $result);
        $this->assertFileExists(__DIR__ . '/docs/multi/index.html');
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
