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

    public function testDescriptorBackedTermIsMarkedAndCarriesItsDescriptorAsClass(): void
    {
        $html = (new TermUsageHtmlRenderer())->render(
            ['emptyDescriptor' => ['parameter: GET /empty {emptyDescriptor}' => true]],
            [],
            ['emptyDescriptor' => []],
            1,
            '100',
        );

        // ALPS-defined terms get a checkmark plus the descriptor id as class.
        $this->assertStringContainsString('class="emptyDescriptor"', $html);
        $this->assertStringContainsString('<span class="alps" title="defined in ALPS">&#x2611;</span>', $html);
        // An empty descriptor contributes no attribute lines, so the dd opens straight into usages.
        $this->assertStringContainsString('<dd><ul>', $html);
    }

    public function testDescriptorIsRenderedAsLabelledAttributesWithDefLinkedWhenUrl(): void
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

        // The term name stays plain; only the def value is a link.
        $this->assertStringContainsString('<dt id="term-familyName" class="familyName"><code>familyName</code><span class="alps" title="defined in ALPS">&#x2611;</span></dt>', $html);
        $this->assertStringContainsString('<p>def: <a href="https://schema.org/familyName">https://schema.org/familyName</a></p>', $html);
        // A non-URL def is shown as a labelled attribute, in field order (title, def, doc).
        $this->assertStringContainsString('<p>def: identifier</p><p>doc: A role within the org</p>', $html);
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
