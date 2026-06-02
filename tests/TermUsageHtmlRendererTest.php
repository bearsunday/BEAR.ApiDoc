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

    public function testEmptyDescriptorDoesNotRenderDescriptorList(): void
    {
        $html = (new TermUsageHtmlRenderer())->render(
            ['emptyDescriptor' => ['parameter: GET /empty {emptyDescriptor}' => true]],
            [],
            ['emptyDescriptor' => []],
            1,
            '100',
        );

        $this->assertStringContainsString('<h3><code>emptyDescriptor</code><span class="alps" title="defined in ALPS">&#x2611;</span></h3>', $html);
        $this->assertStringNotContainsString('class="descriptor"', $html);
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
