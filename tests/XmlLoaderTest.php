<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\ConfigException;
use BEAR\ApiDoc\Exception\ConfigNotFoundException;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

use function chdir;
use function dirname;

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
}
