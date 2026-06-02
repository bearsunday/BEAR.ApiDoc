<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

use function str_replace;

final class TermUsageIndexTest extends TestCase
{
    private string $markdown;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/apidoc.xml');
        $this->markdown = (new TermUsageIndex($config))->generateMarkdown();
    }

    public function testParameterUsagesFromResourceMethodsAndInputExpansion(): void
    {
        $this->assertStringContainsString('### `firstName`', $this->markdown);
        $this->assertStringContainsString('- parameter: POST /person {firstName}', $this->markdown);
        $this->assertStringContainsString('- parameter: PATCH /person {familyName}', $this->markdown);
        $this->assertStringContainsString('- parameter: POST /contact {name}', $this->markdown);
    }

    public function testSchemaPropertyUsagesFromConfiguredSchemaDirs(): void
    {
        $this->assertStringContainsString('- schema property: person.json#/properties/firstName', $this->markdown);
        $this->assertStringContainsString('- schema property: person.param.json#/properties/familyName', $this->markdown);
    }

    public function testAlpsDescriptorLexicalMatchesAndSummary(): void
    {
        $markdown = self::normalizeNewlines($this->markdown);

        $this->assertStringContainsString('This index reports lexical identifier matches only; it does not prove semantic equivalence.', $markdown);
        $this->assertStringContainsString('- Terms used in API:', $markdown);
        $this->assertStringContainsString('- Terms with same-name ALPS descriptor:', $markdown);
        $this->assertStringContainsString('- Lexical ALPS coverage:', $markdown);
        $this->assertStringContainsString('- Reserved representation fields:', $markdown);
        $this->assertStringContainsString('- ☑︎ = ALPS descriptor binding', $markdown);
        $this->assertStringContainsString("### `firstName` ☑︎\n\n- title: firstName by ALPS", $markdown);
        $this->assertStringContainsString("### `familyName` ☑︎\n\n- def: https://schema.org/familyName", $markdown);
        $this->assertStringContainsString("### `age` ☑︎\n\n- doc: Age in years which must be equal to or greater than zero.", $markdown);
        $this->assertStringContainsString("### `foo` ☑︎\n\n- doc: Foo descriptor", $markdown);
        $this->assertStringContainsString("### `assignee`\n\n- usages:", $markdown);
    }

    public function testReservedRepresentationFieldsAreSeparatedFromApiTerms(): void
    {
        $markdown = self::normalizeNewlines($this->markdown);

        $this->assertStringContainsString('- Reserved representation fields: 2', $markdown);
        $this->assertStringNotContainsString('### `_embedded`', $markdown);
        $this->assertStringNotContainsString('### `_links`', $markdown);
        $this->assertStringContainsString("## Reserved Representation Fields\n\nLeading-underscore fields are listed separately", $markdown);
        $this->assertStringContainsString("### Field: `_embedded`\n\n- usages:\n  - schema property: user.json#/properties/_embedded", $markdown);
        $this->assertStringContainsString("### Field: `_links`\n\n- usages:\n  - schema property: user.json#/properties/_links", $markdown);
    }

    public function testMarkdownIsDeterministic(): void
    {
        $config = new Config(__DIR__ . '/apidoc.xml');

        $this->assertSame($this->markdown, (new TermUsageIndex($config))->generateMarkdown());
    }

    public function testHtmlIndexRendersTermsAndBackLink(): void
    {
        $config = new Config(__DIR__ . '/apidoc.xml');
        $html = (new TermUsageIndex($config))->generateHtml();

        $this->assertStringContainsString('<title>Term Usage Index</title>', $html);
        $this->assertStringContainsString('<a href="index.html">API Documentation</a>', $html);
        $this->assertStringContainsString('<li>Terms used in API:', $html);
        // The Index links each term (and reserved field) down to its entry anchor.
        $this->assertStringContainsString('<ul class="index-list">', $html);
        $this->assertStringContainsString('<li><a href="#term-firstName">firstName</a></li>', $html);
        $this->assertStringContainsString('<li><a href="#field-_links">_links</a></li>', $html);
        $this->assertStringContainsString('<dt id="term-firstName" class="firstName"><code>firstName</code><span class="alps" title="defined in ALPS">&#x2611;</span></dt>', $html);
        $this->assertStringContainsString('<dd><p>title: firstName by ALPS</p>', $html);
        $this->assertStringContainsString('<li>parameter: POST /person {firstName}</li>', $html);
        $this->assertStringContainsString('<h2>Reserved Representation Fields</h2>', $html);
    }

    public function testMissingInputsProduceEmptySummary(): void
    {
        $markdown = (new TermUsageIndex(TestConfigFactory::new()))->generateMarkdown();

        $this->assertStringContainsString('- Terms used in API: 0', $markdown);
        $this->assertStringContainsString('- Terms with same-name ALPS descriptor: 0', $markdown);
        $this->assertStringContainsString('- Lexical ALPS coverage: 0%', $markdown);
        $this->assertStringContainsString('- Reserved representation fields: 0', $markdown);
        $this->assertStringNotContainsString('### `', $markdown);
        $this->assertStringNotContainsString('## Reserved Representation Fields', $markdown);
    }

    public function testXmlAlpsDescriptorsAreMatchedLexically(): void
    {
        $config = TestConfigFactory::new([
            'alps' => __DIR__ . '/term-usage.alps.xml',
            'resourceFiles' => [(object) ['uriPath' => 'xml-term', 'class' => XmlTermResource::class]],
            'routes' => ['xml-term' => '/xml-term/{id}'],
        ]);
        $markdown = (new TermUsageIndex($config))->generateMarkdown();

        $this->assertStringContainsString('### `id` ☑︎', $markdown);
        $this->assertStringContainsString('- title: Identifier', $markdown);
        $this->assertStringContainsString('- def: https://schema.org/identifier', $markdown);
        $this->assertStringContainsString('- doc: Identifier.', $markdown);
        $this->assertStringContainsString('- parameter: GET /xml-term/{id} {id}', $markdown);
    }

    public function testInvalidXmlAlpsProfileIsIgnored(): void
    {
        $config = TestConfigFactory::new([
            'alps' => __DIR__ . '/invalid-alps.xml',
        ]);
        $markdown = (new TermUsageIndex($config))->generateMarkdown();

        $this->assertStringContainsString('- Terms used in API: 0', $markdown);
        $this->assertStringContainsString('- Terms with same-name ALPS descriptor: 0', $markdown);
    }

    private static function normalizeNewlines(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }
}
