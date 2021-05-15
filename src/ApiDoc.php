<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Exception\AlpsFileNotFoundException;
use Doctrine\Common\Annotations\AnnotationReader;
use FilesystemIterator;
use Generator;
use Koriym\AppStateDiagram\MdToHtml;
use Koriym\AppStateDiagram\Profile;
use Koriym\AppStateDiagram\SemanticDescriptor;
use Koriym\Attributes\AttributeReader;
use Koriym\Attributes\DualReader;
use RecursiveDirectoryIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

use function array_unique;
use function assert;
use function chmod;
use function copy;
use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_writable;
use function mkdir;
use function sprintf;
use function substr;
use function touch;

final class ApiDoc
{
    public function __invoke(string $configFile): string
    {
        $config = new Config($configFile);
        $docClass = new DocClass(
            new DualReader(new AttributeReader(), new AnnotationReader()),
            $config->requestSchemaDir,
            $config->responseSchemaDir,
            new ModelRepository()
        );
        $this->dump($config, $docClass);

        return sprintf('ApiDoc generated. %s/index.html', $config->docDir);
    }

    private function dump(Config $config, DocClass $docClass): void
    {
        $this->mkDir($config->docDir);

        if ($config->format === 'md') {
            $this->dumpMd($config, $docClass);

            return;
        }

        $this->dumpHtml($config, $docClass);
    }

    public function dumpMd(Config $config, DocClass $docClass): void
    {
        $genMarkDown = $this->getGenMarkdown($config, 'md', $docClass);
        foreach ($genMarkDown as $file => [$markdown]) {
            $this->filePutContents($file . '.md', $markdown);
        }
    }

    public function dumpHtml(Config $config, DocClass $docClass): void
    {
        $genMarkDown = $this->getGenMarkdown($config, 'html', $docClass);
        $mdToHtml = new MdToHtml();
        foreach ($genMarkDown as $file => [$markdown, $path]) {
            $title = sprintf('%s %s', $config->appName, $path);
            $html = $mdToHtml($title, $markdown);
            $this->filePutContents($file . '.html', $html);
        }
    }

    private function filePutContents(string $file, string $contents): void
    {
        touch($file);
        if (! is_writable($file)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException($file);
            // @codeCoverageIgnoreEnd
        }

        file_put_contents($file, $contents);
    }

    private function mkDir(string $docDir): void
    {
        $dir = sprintf('%s/paths', $docDir);
        if (is_dir($dir)) {
            return;
        }

        // @codeCoverageIgnoreStart
        mkdir($dir, 0777, true);
        chmod(dirname($dir), 0777);
        chmod($dir, 0777);
    }

        // @codeCoverageIgnoreEnd

    /**
     * @return Generator<string, array{0: string, 1:string}>
     */
    private function getGenMarkdown(Config $config, string $ext, DocClass $docClass): Generator
    {
        /** @var ArrayObject<string, string> $nullDictionary */
        $nullDictionary = new ArrayObject();
        $semanticDictionary = $config->alps  ? $this->registerAlpsProfile($config->alps) : $nullDictionary;
        $paths = [];
        foreach ($config->resourceFiles as $meta) {
            $path = $config->routes[$meta->uriPath] ?? $meta->uriPath;
            $markdown = $docClass($path, new ReflectionClass($meta->class), $semanticDictionary, $ext);
            $file = sprintf('%s/paths/%s', $config->docDir, substr($meta->uriPath, 1));
            $paths[$path] = substr($meta->uriPath, 1);

            yield $file => [$markdown, $path];
        }

        if ($config->responseSchemaDir) {
            $this->copySchemas($config);
        }

        /** @var list<string> $objects */
        $objects = array_unique((array) $config->modelRepository);

        $index = (string) new Index($config, $paths, $objects, $ext);

        yield sprintf('%s/index', $config->docDir) => [$index, ''];
    }

    private function copySchemas(Config $config): void
    {
        $outputDir = sprintf('%s/schema', $config->docDir);
        ! is_dir($outputDir) && ! mkdir($outputDir) && ! is_dir($outputDir);
        $this->copySchema($config->responseSchemaDir, $outputDir);
    }

    /**
     * @return ArrayObject<string, string>
     */
    private function registerAlpsProfile(string $file): ArrayObject
    {
        if (! file_exists($file)) {
            // @codeCoverageIgnoreStart
            throw new AlpsFileNotFoundException($file);
            // @codeCoverageIgnoreEnd
        }

        $alps = new Profile($file);
        /** @var  ArrayObject<string, string> $semanticDictionary */
        $semanticDictionary = new ArrayObject();
        foreach ($alps->descriptors as $descriptor) {
            if ($descriptor instanceof SemanticDescriptor) {
                $semanticDictionary[$descriptor->id] = $this->getSemanticTitle($descriptor);
            }
        }

        return $semanticDictionary;
    }

    private function getSemanticTitle(SemanticDescriptor $descriptor): string
    {
        if ($descriptor->title) {
            return $descriptor->title;
        }

        if (isset($descriptor->doc->value)) {
            return (string) $descriptor->doc->value;
        }

        if (isset($descriptor->def)) {
            return sprintf('[%s](%s)', $descriptor->def, $descriptor->def);
        }

        return '';
    }

    private function copySchema(string $inputDir, string $outputDir): void
    {
        foreach (new RecursiveDirectoryIterator($inputDir, FilesystemIterator::SKIP_DOTS) as $file) {
            assert($file instanceof SplFileInfo);
            copy((string) $file, sprintf('%s/%s', $outputDir, $file->getFilename()));
        }
    }
}
