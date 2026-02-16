<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\ConfigException;
use BEAR\ApiDoc\Exception\ConfigNotFoundException;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

use function chdir;
use function dirname;
use function mkdir;
use function rmdir;

class XmlLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $xml = (new XmlLoader())('', dirname(__DIR__) . '/apidoc.xsd');
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
    }

    public function testInvalidXml(): void
    {
        $this->expectException(ConfigException::class);
        (new XmlLoader())(__DIR__ . '/apidoc.error.xml', dirname(__DIR__) . '/apidoc.xsd');
    }

    public function testInvalidXmlPath(): void
    {
        chdir('/');
        $this->expectException(ConfigNotFoundException::class);
        (new XmlLoader())('/__INVALID__', dirname(__DIR__) . '/apidoc.xsd');
    }

    public function testFindApidocXmlInParentDirectory(): void
    {
        // Change to a subdirectory that doesn't have apidoc.xml
        // The search will find tests/apidoc.xml (not .dist)
        chdir(__DIR__ . '/Fake');
        $xml = (new XmlLoader())('', dirname(__DIR__) . '/apidoc.xsd');
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
    }

    public function testFindConfigInCurrentWorkingDirectory(): void
    {
        // Change to tests directory
        chdir(__DIR__);
        // Load using relative path from cwd
        $xml = (new XmlLoader())('apidoc.xml', dirname(__DIR__) . '/apidoc.xsd');
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
    }

    public function testFindDistConfigInParentDirectory(): void
    {
        // Search from a subdirectory of dist-only to find apidoc.xml.dist
        $distOnlyDir = __DIR__ . '/Fake/dist-only';
        $subDir = $distOnlyDir . '/sub';
        @mkdir($subDir, 0777, true);
        chdir($subDir);
        $xml = (new XmlLoader())('', dirname(__DIR__) . '/apidoc.xsd');
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        @rmdir($subDir);
    }
}
