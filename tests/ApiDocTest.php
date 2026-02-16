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

    public function testMultipleFormatsOutput(): void
    {
        $apiDoc = new ApiDoc();
        $result = $apiDoc(__DIR__ . '/apidoc.multi.xml');

        $this->assertStringContainsString('index.html', $result);
        $this->assertStringContainsString('llms.txt', $result);
        $this->assertFileExists(__DIR__ . '/docs/multi/index.html');
        $this->assertFileExists(__DIR__ . '/docs/multi/llms.txt');
    }
}
