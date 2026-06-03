<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

use function strpos;

final class ApiDocAuditTest extends TestCase
{
    public function testGenerateMarkdownReportsSummaryAndFindings(): void
    {
        $report = (new ApiDocAudit(new Config(__DIR__ . '/apidoc.alps.xml')))->generateMarkdown();

        $this->assertStringContainsString('# API Documentation Audit', $report);
        $this->assertMatchesRegularExpression('/- Resources: \d+/', $report);
        $this->assertStringContainsString('- Operations: 28', $report);
        $this->assertStringContainsString('- Operations with response schema: 11', $report);
        $this->assertStringContainsString('- Operations with request schema: 12', $report);
        $this->assertStringContainsString('- Operations with ALPS attributes: 6', $report);

        $this->assertStringContainsString("### POST /contact\n- Missing response schema.\n- Missing request schema for non-path body input.\n- Missing resource class summary.\n- Missing operation summary.\n- Missing ALPS attribute.", $report);
        $this->assertStringContainsString("### PATCH /person\n- Missing response schema.\n- Missing ALPS attribute.", $report);
        $this->assertStringContainsString("### GET /tickets\n- Missing resource class summary.\n- Missing operation summary.\n- Missing ALPS attribute.", $report);
    }

    public function testGenerateMarkdownOrdersOperationsByPathAndMethod(): void
    {
        $report = (new ApiDocAudit(new Config(__DIR__ . '/apidoc.alps.xml')))->generateMarkdown();

        $address = strpos($report, '### GET /address');
        $contact = strpos($report, '### POST /contact');
        $personPatch = strpos($report, '### PATCH /person');
        $personPost = strpos($report, '### POST /person');
        $ticketDelete = strpos($report, '### DELETE /ticket/{id}');
        $ticketGet = strpos($report, '### GET /ticket/{id}');

        $this->assertIsInt($address);
        $this->assertIsInt($contact);
        $this->assertIsInt($personPatch);
        $this->assertIsInt($personPost);
        $this->assertIsInt($ticketDelete);
        $this->assertIsInt($ticketGet);
        $this->assertLessThan($contact, $address);
        $this->assertLessThan($personPost, $personPatch);
        $this->assertLessThan($ticketGet, $ticketDelete);
    }

    public function testAlpsCountsAndFindingsAreOnlyReportedWhenProfileIsConfigured(): void
    {
        $report = (new ApiDocAudit(new Config(__DIR__ . '/apidoc.html-nolinks.xml')))->generateMarkdown();

        $this->assertStringNotContainsString('Operations with ALPS attributes', $report);
        $this->assertStringNotContainsString('Missing ALPS attribute.', $report);
    }

    public function testAlpsStringZeroIsTreatedAsDisabled(): void
    {
        $config = TestConfigFactory::new([
            'alps' => '0',
            'resourceFiles' => [(object) ['uriPath' => 'no-alps', 'class' => NoAlpsAuditResource::class]],
            'routes' => ['no-alps' => '/no-alps/{id}'],
        ]);
        $report = (new ApiDocAudit($config))->generateMarkdown();

        $this->assertStringNotContainsString('Operations with ALPS attributes', $report);
        $this->assertStringNotContainsString('Missing ALPS attribute.', $report);
        $this->assertStringContainsString("## Findings\nNo documentation gaps found.", $report);
    }

    public function testGenerateMarkdownReportsNoFindingsForDocumentedOperations(): void
    {
        $config = TestConfigFactory::new([
            'alps' => __FILE__,
            'resourceFiles' => [(object) ['uriPath' => 'documented', 'class' => DocumentedAuditResource::class]],
            'routes' => ['documented' => '/documented/{id}'],
        ]);
        $report = (new ApiDocAudit($config))->generateMarkdown();

        $this->assertStringContainsString('- Resources: 1', $report);
        $this->assertStringContainsString('- Operations: 1', $report);
        $this->assertStringContainsString('- Operations with response schema: 1', $report);
        $this->assertStringContainsString('- Operations with request schema: 1', $report);
        $this->assertStringContainsString('- Operations with ALPS attributes: 1', $report);
        $this->assertStringContainsString("## Findings\nNo documentation gaps found.", $report);
        $this->assertStringNotContainsString('Missing ', $report);
    }

    public function testClassLevelAlpsAttributeCountsAsOperationAlpsCoverage(): void
    {
        $config = TestConfigFactory::new([
            'alps' => __FILE__,
            'resourceFiles' => [(object) ['uriPath' => 'class-level-alps', 'class' => ClassLevelAlpsAuditResource::class]],
            'routes' => ['class-level-alps' => '/class-level-alps/{id}'],
        ]);
        $report = (new ApiDocAudit($config))->generateMarkdown();

        $this->assertStringContainsString('- Operations with ALPS attributes: 1', $report);
        $this->assertStringContainsString("## Findings\nNo documentation gaps found.", $report);
        $this->assertStringNotContainsString('Missing ALPS attribute.', $report);
    }

    public function testGenerateHtmlDeclaresProfileAndBindsReportVocabulary(): void
    {
        $html = (new ApiDocAudit(new Config(__DIR__ . '/apidoc.alps.xml')))->generateHtml();

        // The audit page declares its own per-report profile and binds the document as a whole.
        $this->assertStringContainsString('<link rel="profile" href="https://bearsunday.github.io/BEAR.ApiDoc/alps/audit.xml">', $html);
        $this->assertStringContainsString('<main class="apiDocumentationAudit">', $html);
        // Summary figures bind to their count descriptors.
        $this->assertStringContainsString('<li class="operationCount">Operations: 28</li>', $html);
        $this->assertStringContainsString('<li class="alpsAttributeCount">Operations with ALPS attributes: 6</li>', $html);
        // Each operation with gaps is a bound section; each finding carries a bound type token.
        $this->assertStringContainsString('<section class="operation" id="op-POST-contact"><h3>POST /contact</h3>', $html);
        $this->assertStringContainsString('<li class="finding"><code class="findingType">response-schema</code> Missing response schema.</li>', $html);
        $this->assertStringContainsString('<li class="finding"><code class="findingType">alps</code> Missing ALPS attribute.</li>', $html);
    }

    public function testGenerateHtmlOmitsAlpsFiguresWhenProfileDisabled(): void
    {
        $html = (new ApiDocAudit(new Config(__DIR__ . '/apidoc.html-nolinks.xml')))->generateHtml();

        $this->assertStringNotContainsString('alpsAttributeCount', $html);
        $this->assertStringNotContainsString('findingType">alps<', $html);
    }

    public function testGenerateHtmlReportsNoGapsForDocumentedOperations(): void
    {
        $config = TestConfigFactory::new([
            'alps' => __FILE__,
            'resourceFiles' => [(object) ['uriPath' => 'documented', 'class' => DocumentedAuditResource::class]],
            'routes' => ['documented' => '/documented/{id}'],
        ]);
        $html = (new ApiDocAudit($config))->generateHtml();

        $this->assertStringContainsString('<p>No documentation gaps found.</p>', $html);
        $this->assertStringNotContainsString('class="operation"', $html);
    }

    public function testHtmlIsDeterministic(): void
    {
        $config = new Config(__DIR__ . '/apidoc.alps.xml');

        $this->assertSame(
            (new ApiDocAudit($config))->generateHtml(),
            (new ApiDocAudit($config))->generateHtml(),
        );
    }
}
