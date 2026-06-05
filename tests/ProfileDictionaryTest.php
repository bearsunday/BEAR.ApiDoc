<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use AlpsAsd\AlpsProfile\Exception\InvalidProfileException;
use AlpsAsd\AlpsProfile\ProfileDictionary;
use PHPUnit\Framework\TestCase;

use function assert;
use function basename;
use function file_put_contents;
use function sprintf;
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
  <descriptor id="refFallback" ref="https://schema.org/ref"/>
  <descriptor id="srcFallback" src="https://schema.org/src"/>
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
        $this->assertSame('[https://schema.org/ref](https://schema.org/ref)', $dictionary['refFallback']);
        $this->assertSame('[https://schema.org/src](https://schema.org/src)', $dictionary['srcFallback']);
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

    public function testJsonRefAndSrcAreUsedAsDefFallback(): void
    {
        $profile = <<<'JSON'
{
  "alps": {
    "descriptor": [
      {"id": "byRef", "ref": "https://schema.org/ref"},
      {"id": "bySrc", "src": "https://schema.org/src"}
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

        $this->assertSame('[https://schema.org/ref](https://schema.org/ref)', $dictionary['byRef']);
        $this->assertSame('[https://schema.org/src](https://schema.org/src)', $dictionary['bySrc']);
    }

    public function testResolvesExternalHrefReference(): void
    {
        $external = $this->writeTempFile('{"alps":{"descriptor":[{"id":"sharedName","title":"Shared from external"}]}}', '.json');
        $main = $this->writeTempFile(sprintf('{"alps":{"descriptor":[{"href":"%s#sharedName"}]}}', basename($external)), '.json');

        try {
            $dictionary = ProfileDictionary::fromFile($main)->toArray();
        } finally {
            @unlink($main);
            @unlink($external);
        }

        $this->assertSame('Shared from external', $dictionary['sharedName']);
    }

    public function testResolvesExternalRtReference(): void
    {
        $external = $this->writeTempFile('{"alps":{"descriptor":[{"id":"SharedState","title":"Shared state"}]}}', '.json');
        $main = $this->writeTempFile(
            sprintf('{"alps":{"descriptor":[{"id":"goShared","type":"safe","rt":"%s#SharedState","title":"Go shared"}]}}', basename($external)),
            '.json',
        );

        try {
            $dictionary = ProfileDictionary::fromFile($main)->toArray();
        } finally {
            @unlink($main);
            @unlink($external);
        }

        $this->assertSame('Go shared', $dictionary['goShared']);
        $this->assertSame('Shared state', $dictionary['SharedState']);
    }

    public function testResolvesExternalHrefNestedInsideParentDescriptor(): void
    {
        $external = $this->writeTempFile('{"alps":{"descriptor":[{"id":"city","title":"City name"}]}}', '.json');
        $main = $this->writeTempFile(
            sprintf('{"alps":{"descriptor":[{"id":"Address","title":"Address","descriptor":[{"href":"%s#city"}]}]}}', basename($external)),
            '.json',
        );

        try {
            $dictionary = ProfileDictionary::fromFile($main)->toArray();
        } finally {
            @unlink($main);
            @unlink($external);
        }

        $this->assertSame('Address', $dictionary['Address']);
        $this->assertSame('City name', $dictionary['city']);
    }

    public function testResolvesExternalXmlHrefAndRtReferences(): void
    {
        $external = $this->writeTempFile(
            '<?xml version="1.0"?><alps version="1.0">'
            . '<descriptor id="sharedName" title="Shared from external XML"/>'
            . '<descriptor id="SharedState" title="Shared XML state"/>'
            . '</alps>',
            '.xml',
        );
        $main = $this->writeTempFile(
            sprintf(
                '<?xml version="1.0"?><alps version="1.0">'
                . '<descriptor href="%1$s#sharedName"/>'
                . '<descriptor id="goShared" type="safe" rt="%1$s#SharedState" title="Go shared"/>'
                . '</alps>',
                basename($external),
            ),
            '.xml',
        );

        try {
            $dictionary = ProfileDictionary::fromFile($main)->toArray();
        } finally {
            @unlink($main);
            @unlink($external);
        }

        $this->assertSame('Shared from external XML', $dictionary['sharedName']);
        $this->assertSame('Go shared', $dictionary['goShared']);
        $this->assertSame('Shared XML state', $dictionary['SharedState']);
    }

    public function testRemoteHrefReferenceIsNotFetched(): void
    {
        $profile = '{"alps":{"descriptor":[{"id":"local","title":"Local"},{"href":"https://example.com/common.json#remote"}]}}';
        $file = $this->writeTempFile($profile, '.json');

        try {
            $dictionary = ProfileDictionary::fromFile($file)->toArray();
        } finally {
            @unlink($file);
        }

        $this->assertSame('Local', $dictionary['local']);
        $this->assertArrayNotHasKey('remote', $dictionary);
    }

    public function testFromFileTreatsNonXmlExtensionAsJson(): void
    {
        $file = $this->writeTempFile('{"alps":{"descriptor":[{"id":"upper","title":"Upper JSON"}]}}', '.JSON');

        try {
            $dictionary = ProfileDictionary::fromFile($file)->toArray();
        } finally {
            @unlink($file);
        }

        $this->assertSame('Upper JSON', $dictionary['upper']);
    }

    public function testInvalidJsonProfileThrows(): void
    {
        $file = $this->writeTempFile('{ this is : not json', '.json');

        $this->expectException(InvalidProfileException::class);

        try {
            ProfileDictionary::fromFile($file);
        } finally {
            @unlink($file);
        }
    }

    public function testInvalidXmlProfileThrows(): void
    {
        $file = $this->writeTempFile('<alps><descriptor', '.xml');

        $this->expectException(InvalidProfileException::class);

        try {
            ProfileDictionary::fromFile($file);
        } finally {
            @unlink($file);
        }
    }

    public function testUnreadableProfileThrows(): void
    {
        $this->expectException(InvalidProfileException::class);

        ProfileDictionary::fromJsonFile(sys_get_temp_dir() . '/bear-apidoc-missing-profile.json');
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
