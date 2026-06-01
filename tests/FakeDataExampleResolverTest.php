<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function assert;
use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class FakeDataExampleResolverTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sprintf('%s/bear-apidoc-fake-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        mkdir($this->dir . '/fake', 0777, true);
        mkdir($this->dir . '/docs', 0777, true);
        mkdir($this->dir . '/schema', 0777, true);
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            $path = $file->getPathname();
            if ($file->isDir()) {
                rmdir($path);

                continue;
            }

            unlink($path);
        }

        rmdir($this->dir);
    }

    public function testObjectPayloadReferencesSourceFile(): void
    {
        $this->writeJson('fake/article.json', ['id' => 1, 'title' => 'Hello']);
        $resolver = $this->resolver();
        $example = $resolver->responseExample('article.json', $this->schema('article.json', 'object', ['id', 'title']));

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('ArticleFake', $example->componentName);
        $this->assertSame('../fake/article.json', $example->externalValue);
        $this->assertFileDoesNotExist($this->dir . '/docs/examples/article.json');
    }

    public function testListPayloadForListResponseReferencesSourceFile(): void
    {
        $this->writeJson('fake/articles.json', [['id' => 1], ['id' => 2]]);
        $resolver = $this->resolver();
        $example = $resolver->responseExample('articles.json', $this->schema('articles.json', 'array'));

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('ArticlesFake', $example->componentName);
        $this->assertSame('../fake/articles.json', $example->externalValue);
        $this->assertFileDoesNotExist($this->dir . '/docs/examples/articles.json');
    }

    public function testListPayloadForObjectResponseWritesFirstItemExample(): void
    {
        $this->writeJson('fake/article.json', [['id' => 1, 'title' => 'Hello'], ['id' => 2, 'title' => 'Bye']]);
        $resolver = $this->resolver();
        $example = $resolver->responseExample('article.json', $this->schema('article.json', 'object', ['id', 'title']));

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('ArticleFake', $example->componentName);
        $this->assertSame('./examples/article.json', $example->externalValue);
        $this->assertSame(['id' => 1, 'title' => 'Hello'], $this->readJson('docs/examples/article.json'));
    }

    public function testRequestProjectionWritesOnlyRequestBodyProperties(): void
    {
        $this->writeJson('fake/article.json', ['id' => 1, 'title' => 'Hello', 'body' => 'Text', 'created' => 'now']);
        $resolver = $this->resolver();
        $example = $resolver->requestExample(
            'article.param.json',
            $this->schema('article.param.json', 'object', ['id', 'title', 'body']),
            '',
            ['title', 'body'],
        );

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('ArticleParamFake', $example->componentName);
        $this->assertSame('./examples/article.param.json', $example->externalValue);
        $this->assertSame(['title' => 'Hello', 'body' => 'Text'], $this->readJson('docs/examples/article.param.json'));
    }

    public function testCompatibleRequestPayloadReferencesSourceFile(): void
    {
        $this->writeJson('fake/article.param.json', ['title' => 'Hello', 'body' => 'Text']);
        $resolver = $this->resolver();
        $example = $resolver->requestExample(
            'article.param.json',
            $this->schema('article.param.json', 'object', ['title', 'body']),
            '',
            ['title', 'body'],
        );

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('../fake/article.param.json', $example->externalValue);
        $this->assertFileDoesNotExist($this->dir . '/docs/examples/article.param.json');
    }

    public function testRequestProjectionUsesExplicitResponseSchemaFallback(): void
    {
        $this->writeJson('fake/result.json', ['title' => 'Hello', 'body' => 'Text', 'extra' => true]);
        $resolver = $this->resolver();
        $example = $resolver->requestExample(
            'create.param.json',
            $this->schema('create.param.json', 'object', ['title']),
            'result.json',
            ['title'],
        );

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('./examples/create.param.json', $example->externalValue);
        $this->assertSame(['title' => 'Hello'], $this->readJson('docs/examples/create.param.json'));
    }

    public function testRequestProjectionUsesFirstObjectFromListPayload(): void
    {
        $this->writeJson('fake/article.json', [['title' => 'Hello', 'extra' => true]]);
        $resolver = $this->resolver();
        $example = $resolver->requestExample(
            'article.param.json',
            $this->schema('article.param.json', 'object', ['title']),
            '',
            ['title'],
        );

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('./examples/article.param.json', $example->externalValue);
        $this->assertSame(['title' => 'Hello'], $this->readJson('docs/examples/article.param.json'));
    }

    public function testExternalValueRelativeToBaseDirectory(): void
    {
        $example = new FakeDataExample('ArticleFake', 'summary', './fake/article.json');

        $this->assertSame('./fake/article.json', $example->externalValueRelativeTo(''));
        $this->assertSame('./fake/article.json', $example->externalValueRelativeTo('.'));
    }

    public function testUnsupportedResponseAndRequestPayloadsAreSkipped(): void
    {
        $resolver = $this->resolver();

        $this->writeJson('fake/object-for-array.json', ['id' => 1]);
        $this->assertNull($resolver->responseExample('object-for-array.json', $this->schema('object-for-array.json', 'array')));

        $this->writeJson('fake/list-param.json', [['not-object']]);
        $this->assertNull($resolver->requestExample(
            'list-param.json',
            $this->schema('list-param.json', 'object', ['title']),
            '',
            ['title'],
        ));

        $this->writeJson('fake/no-overlap.param.json', ['other' => true]);
        $this->assertNull($resolver->requestExample(
            'no-overlap.param.json',
            $this->schema('no-overlap.param.json', 'object', ['title']),
            '',
            ['title'],
        ));
    }

    public function testMissingMalformedAndEmptyListPayloadsAreSkipped(): void
    {
        $resolver = $this->resolver();
        $schema = $this->schema('missing.json', 'object', ['id']);
        $this->assertNull($resolver->responseExample('missing.json', $schema));

        file_put_contents($this->dir . '/fake/empty-file.json', '');
        $this->assertNull($resolver->responseExample('empty-file.json', $this->schema('empty-file.json', 'object', ['id'])));

        file_put_contents($this->dir . '/fake/malformed.json', '{');
        $this->assertNull($resolver->responseExample('malformed.json', $this->schema('malformed.json', 'object', ['id'])));

        $this->writeJson('fake/empty.json', []);
        $this->assertNull($resolver->responseExample('empty.json', $this->schema('empty.json', 'object', ['id'])));

        $this->assertNull($resolver->requestExample(
            'custom.json',
            $this->schema('custom.json', 'object', ['id']),
            '',
            ['id'],
        ));
    }

    public function testBlankComponentBaseNameFallsBackToPayloadName(): void
    {
        $this->writeJson('fake/---.json', ['id' => 1]);
        $resolver = $this->resolver();
        $example = $resolver->responseExample('---.json', $this->schema('---.json', 'object', ['id']));

        $this->assertInstanceOf(FakeDataExample::class, $example);
        $this->assertSame('PayloadFake', $example->componentName);
    }

    private function resolver(): FakeDataExampleResolver
    {
        return new FakeDataExampleResolver($this->dir . '/fake', $this->dir . '/docs');
    }

    /** @param list<string> $properties */
    private function schema(string $file, string $type, array $properties = []): Schema
    {
        $schema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => $file,
            'type' => $type,
        ];
        if ($properties !== []) {
            $schema['properties'] = [];
            foreach ($properties as $property) {
                $schema['properties'][$property] = ['type' => 'string'];
            }
        }

        $schemaFile = 'schema/' . $file;
        $this->writeJson($schemaFile, $schema);
        $json = file_get_contents($this->dir . '/' . $schemaFile);
        $this->assertIsString($json);

        /** @var object $schemaObject */
        $schemaObject = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        /** @var ArrayObject<string, string> $semanticDictionary */
        $semanticDictionary = new ArrayObject();

        return new Schema(new SplFileInfo($this->dir . '/' . $schemaFile), $schemaObject, $semanticDictionary);
    }

    private function writeJson(string $file, mixed $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        file_put_contents($this->dir . '/' . $file, $json);
    }

    private function readJson(string $file): mixed
    {
        $json = file_get_contents($this->dir . '/' . $file);
        $this->assertIsString($json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
