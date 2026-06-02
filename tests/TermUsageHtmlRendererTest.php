<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

final class TermUsageHtmlRendererTest extends TestCase
{
    public function testEmptyTermsAndReservedFieldsAreRenderedWithoutSections(): void
    {
        $html = (new TermUsageHtmlRenderer())->render([], [], [], 0, '0');

        $this->assertStringContainsString('<p>No API terms found.</p>', $html);
        $this->assertStringNotContainsString('Reserved Representation Fields', $html);
    }

    public function testDescriptorBackedTermCarriesItsDescriptorAsClassWithoutDescription(): void
    {
        $html = (new TermUsageHtmlRenderer())->render(
            ['emptyDescriptor' => ['parameter: GET /empty {emptyDescriptor}' => true]],
            [],
            ['emptyDescriptor' => []],
            1,
            '100',
        );

        // The same-name ALPS descriptor is the semantic token, not a UI badge.
        $this->assertStringContainsString('class="emptyDescriptor"', $html);
        // An empty descriptor contributes no description, so the dd opens straight into usages.
        $this->assertStringContainsString('<dd><ul>', $html);
    }

    public function testDefRendersAsLinkWhenUrlAndAsDescriptionTextOtherwise(): void
    {
        $html = (new TermUsageHtmlRenderer())->render(
            [
                'familyName' => ['parameter: POST /person {familyName}' => true],
                'role' => ['parameter: POST /person {role}' => true],
            ],
            [],
            [
                'familyName' => ['def' => 'https://schema.org/familyName'],
                'role' => ['def' => 'identifier', 'doc' => 'A role within the org'],
            ],
            2,
            '100',
        );

        // A URL def turns the term name into a link to the definition.
        $this->assertStringContainsString('<dt id="term-familyName" class="familyName"><a href="https://schema.org/familyName"><code>familyName</code></a></dt>', $html);
        // A non-URL def is shown as descriptor text; the term name is not linked.
        $this->assertStringContainsString('<dt id="term-role" class="role"><code>role</code></dt>', $html);
        $this->assertStringContainsString('<dd><p>A role within the org</p><p>identifier</p>', $html);
    }

    /**
     * Parameter-derived terms are always valid PHP identifiers, so symbols never
     * arise from method signatures. However, JSON Schemas are reusable, independent
     * artifacts whose property names are arbitrary JSON keys and may contain symbols.
     * The renderer must therefore sanitize term ids defensively rather than assume
     * an identifier-safe character set.
     */
    public function testHtmlIdsFallbackWhenTermSanitizesToEmptyString(): void
    {
        $html = (new TermUsageHtmlRenderer())->render(
            ['!!!' => ['parameter: GET /symbol {!!!}' => true]],
            ['!!!' => ['schema property: symbol.json#/properties/!!!' => true]],
            ['!!!' => ['title' => 'Symbol term']],
            1,
            '100',
        );

        $this->assertStringContainsString('id="term-empty-', $html);
        $this->assertStringContainsString('id="field-empty-', $html);
        $this->assertStringNotContainsString('id="term-"', $html);
        $this->assertStringNotContainsString('id="field-"', $html);
    }
}
