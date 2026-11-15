<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;

use function assert;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class AlpsProfileLinkExtractorTest extends TestCase
{
    public function testEmptyOrMissingAlpsPathYieldsNoLinks(): void
    {
        $extractor = new AlpsProfileLinkExtractor(new JsonFile());

        $this->assertSame([], $extractor(''));
        $this->assertSame([], $extractor('/no/such/file.json'));
    }

    public function testJsonSkipsNonArrayItemsAndLinksMissingRelOrHref(): void
    {
        $profile = <<<'JSON'
{
  "alps": {
    "link": ["not-an-object", {"href": "no-rel.html"}, {"rel": "no-href"}, {"rel": "home", "href": "index.html"}]
  }
}
JSON;
        $file = $this->writeTempFile($profile, '.json');

        try {
            $links = (new AlpsProfileLinkExtractor(new JsonFile()))($file);
        } finally {
            unlink($file);
        }

        $this->assertSame([
            ['rel' => 'home', 'href' => 'index.html'],
        ], $links);
    }

    public function testJsonSingleLinkObject(): void
    {
        $file = $this->writeTempFile('{"alps": {"link": {"rel": "help", "href": "help.html", "title": "Help"}}}', '.json');

        try {
            $links = (new AlpsProfileLinkExtractor(new JsonFile()))($file);
        } finally {
            unlink($file);
        }

        $this->assertSame([['rel' => 'help', 'href' => 'help.html', 'title' => 'Help']], $links);
    }

    public function testXmlInvalidFileYieldsNoLinks(): void
    {
        $file = $this->writeTempFile('not xml at all', '.xml');

        try {
            $links = @(new AlpsProfileLinkExtractor(new JsonFile()))($file);
        } finally {
            unlink($file);
        }

        $this->assertSame([], $links);
    }

    public function testXmlSkipsLinksMissingRelOrHref(): void
    {
        $profile = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<alps version="1.0">
  <link href="no-rel.html"/>
  <link rel="no-href"/>
  <link rel="home" href="index.html"/>
</alps>
XML;
        $file = $this->writeTempFile($profile, '.xml');

        try {
            $links = (new AlpsProfileLinkExtractor(new JsonFile()))($file);
        } finally {
            unlink($file);
        }

        $this->assertSame([
            ['rel' => 'home', 'href' => 'index.html'],
        ], $links);
    }

    private function writeTempFile(string $contents, string $extension): string
    {
        $baseFile = tempnam(sys_get_temp_dir(), 'bear-apidoc-alps-links-');
        assert($baseFile !== false);
        $file = $baseFile . $extension;
        @unlink($baseFile);
        file_put_contents($file, $contents);

        return $file;
    }
}
