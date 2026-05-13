<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\ApiDoc\Exception\InvalidJsonFileException;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class JsonFileTest extends TestCase
{
    public function testObjectLoadsJsonObject(): void
    {
        $file = $this->writeTempJson('{"title":"Ticket"}');

        try {
            $json = (new JsonFile())->object($file);

            $this->assertSame('Ticket', $json->title ?? null);
        } finally {
            @unlink($file);
        }
    }

    public function testInvalidJsonReportsFileName(): void
    {
        $file = $this->writeTempJson('{');

        $this->expectException(InvalidJsonFileException::class);
        $this->expectExceptionMessage($file);

        try {
            (new JsonFile())->object($file);
        } finally {
            @unlink($file);
        }
    }

    public function testObjectRejectsNonObjectJson(): void
    {
        $file = $this->writeTempJson('[]');

        $this->expectException(InvalidJsonFileException::class);
        $this->expectExceptionMessage('JSON root must be an object');

        try {
            (new JsonFile())->object($file);
        } finally {
            @unlink($file);
        }
    }

    public function testAssocRejectsNonObjectJson(): void
    {
        $file = $this->writeTempJson('"scalar"');

        $this->expectException(InvalidJsonFileException::class);
        $this->expectExceptionMessage('JSON root must be an object');

        try {
            (new JsonFile())->assoc($file);
        } finally {
            @unlink($file);
        }
    }

    public function testAssocRejectsListRoot(): void
    {
        $file = $this->writeTempJson('[]');

        $this->expectException(InvalidJsonFileException::class);
        $this->expectExceptionMessage('JSON root must be an object');

        try {
            (new JsonFile())->assoc($file);
        } finally {
            @unlink($file);
        }
    }

    public function testReadFailureReportsFileName(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'bear-apidoc-missing-json-');
        assert($file !== false);
        unlink($file);

        $this->expectException(InvalidJsonFileException::class);
        $this->expectExceptionMessage($file);

        (new JsonFile())->object($file);
    }

    private function writeTempJson(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'bear-apidoc-json-');
        assert($file !== false);
        file_put_contents($file, $contents);

        return $file;
    }
}
