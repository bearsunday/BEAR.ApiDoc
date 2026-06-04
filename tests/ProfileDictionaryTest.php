<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use AlpsAsd\AlpsProfile\ProfileDictionary;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ProfileDictionaryTest extends TestCase
{
    public function testJsonDictionaryRecursivelyUsesTitleDocAndDefFallbacks(): void
    {
        $profile = <<<'JSON'
{
  "alps": {
    "descriptor": [
      {"id": "titleFirst", "title": "Title wins", "doc": {"value": "Doc loses"}, "def": "https://schema.org/name"},
      {"id": "docSecond", "doc": {"value": "Doc fallback"}, "def": "https://schema.org/description"},
      {"id": "stringDoc", "doc": "String doc fallback"},
      {"id": "defThird", "def": "https://schema.org/identifier"},
      {"id": "empty"},
      {"id": "Parent", "descriptor": [
        {"id": "nested", "title": "Nested title"}
      ]}
    ]
  }
}
JSON;
        $file = $this->writeTempFile($profile, '.json');

        try {
            $dictionary = ProfileDictionary::fromFile($file)->toArray();
        } finally {
            @unlink($file);
        }

        $this->assertSame('Title wins', $dictionary['titleFirst']);
        $this->assertSame('Doc fallback', $dictionary['docSecond']);
        $this->assertSame('String doc fallback', $dictionary['stringDoc']);
        $this->assertSame('[https://schema.org/identifier](https://schema.org/identifier)', $dictionary['defThird']);
        $this->assertSame('', $dictionary['empty']);
        $this->assertSame('Nested title', $dictionary['nested']);
    }

    public function testXmlDictionaryRecursivelyUsesTitleDocAndDefFallbacks(): void
    {
        $profile = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<alps version="1.0">
  <descriptor id="titleFirst" title="Title wins" def="https://schema.org/name">
    <doc>Doc loses</doc>
  </descriptor>
  <descriptor id="docSecond" def="https://schema.org/description">
    <doc>Doc fallback</doc>
  </descriptor>
  <descriptor id="docAttribute">
    <doc value="Attribute doc fallback"/>
  </descriptor>
  <descriptor id="defThird" def="https://schema.org/identifier"/>
  <descriptor id="empty"/>
  <descriptor id="Parent">
    <descriptor id="nested" title="Nested title"/>
  </descriptor>
</alps>
XML;
        $file = $this->writeTempFile($profile, '.xml');

        try {
            $dictionary = ProfileDictionary::fromFile($file)->toArray();
        } finally {
            @unlink($file);
        }

        $this->assertSame('Title wins', $dictionary['titleFirst']);
        $this->assertSame('Doc fallback', $dictionary['docSecond']);
        $this->assertSame('Attribute doc fallback', $dictionary['docAttribute']);
        $this->assertSame('[https://schema.org/identifier](https://schema.org/identifier)', $dictionary['defThird']);
        $this->assertSame('', $dictionary['empty']);
        $this->assertSame('Nested title', $dictionary['nested']);
    }

    public function testArrayObjectKeepsExistingApiDocDictionaryShape(): void
    {
        $file = $this->writeTempFile('{"alps":{"descriptor":[{"id":"firstName","title":"First Name"}]}}', '.json');

        try {
            $dictionary = ProfileDictionary::fromFile($file)->toArrayObject();
        } finally {
            @unlink($file);
        }

        $this->assertSame('First Name', $dictionary['firstName']);
    }

    private function writeTempFile(string $contents, string $extension): string
    {
        $baseFile = tempnam(sys_get_temp_dir(), 'bear-apidoc-alps-profile-');
        assert($baseFile !== false);
        $file = $baseFile . $extension;
        @unlink($baseFile);
        file_put_contents($file, $contents);

        return $file;
    }
}
