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
        $report = (new ApiDocAudit(new Config(__DIR__ . '/apidoc.html.xml')))->generateMarkdown();

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
}
