<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

class SchemaIndexTest extends TestCase
{
    public function testSchemaIndex(): void
    {
        $schemaIndex = new SchemaIndex(__DIR__ . '/Fake/app/docs/base/schema');
        $html = (string) $schemaIndex;

        $this->assertStringContainsString('<title>JSON Schemas</title>', $html);
        $this->assertStringContainsString('class="goSchema">person</a>', $html);
        $this->assertStringContainsString('rel="profile"', $html);
    }

    public function testEmptySchemaDir(): void
    {
        $schemaIndex = new SchemaIndex(__DIR__ . '/nonexistent');
        $html = (string) $schemaIndex;

        $this->assertStringContainsString('<title>JSON Schemas</title>', $html);
        $this->assertStringContainsString('class="schemaList"', $html);
    }
}
