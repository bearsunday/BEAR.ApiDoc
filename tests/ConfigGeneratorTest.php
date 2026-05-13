<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

use function assert;
use function bin2hex;
use function chdir;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_string;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function simplexml_load_string;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

class ConfigGeneratorTest extends TestCase
{
    public function testEscapesComposerDescriptionInGeneratedXml(): void
    {
        $cwd = getcwd();
        assert(is_string($cwd));
        $dir = sprintf('%s/bear-apidoc-config-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        mkdir($dir);

        try {
            file_put_contents(
                $dir . '/composer.json',
                json_encode([
                    'description' => 'A & <B>',
                    'autoload' => [
                        'psr-4' => ['Vendor\\Demo\\' => 'src/'],
                    ],
                ], JSON_THROW_ON_ERROR),
            );

            chdir($dir);
            (new ConfigGenerator())();

            $xml = file_get_contents($dir . '/apidoc.xml');
            $this->assertIsString($xml);
            $this->assertStringContainsString('<description>A &amp; &lt;B&gt;</description>', $xml);
            $this->assertInstanceOf(SimpleXMLElement::class, simplexml_load_string($xml));
        } finally {
            chdir($cwd);
            @unlink($dir . '/apidoc.xml');
            @unlink($dir . '/composer.json');
            @rmdir($dir);
        }
    }
}
