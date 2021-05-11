<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use BEAR\ApiDoc\Exception\AlpsFileNotFoundException;
use BEAR\AppMeta\Meta;
use Doctrine\Common\Annotations\Reader;
use FilesystemIterator;
use Generator;
use Koriym\AppStateDiagram\MdToHtml;
use Koriym\AppStateDiagram\Profile;
use Koriym\AppStateDiagram\SemanticDescriptor;
use Ray\Di\Exception\Unbound;
use Ray\Di\Injector;
use Ray\Di\InjectorInterface;
use RecursiveDirectoryIterator;
use ReflectionClass;
use RuntimeException;
use SimpleXMLElement;
use SplFileInfo;

use function array_unique;
use function assert;
use function class_exists;
use function copy;
use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_iterable;
use function is_string;
use function is_writable;
use function mkdir;
use function simplexml_load_file;
use function sprintf;
use function substr;
use function touch;

final class ApiDoc
{
    /** @var string */
    private $appName;

    /** @var Meta */
    private $meta;

    /** @var DocClass */
    private $docClass;

    /** @var array<string, string> */
    private $routes = [];

    /** @var string */
    private $responseSchemaDir;

    /** @var ArrayObject<string, string> */
    private $modelRepository;

    /** @var Config */
    private $config;

    /** @var string */
    private $dir;

    public function __construct(string $xmlFile)
    {
        $xml = simplexml_load_file($xmlFile);
        assert($xml instanceof SimpleXMLElement);
        $this->dir = dirname($xmlFile);
        $this->config = new Config($xml);
        $appName = $this->config->appName;
        $this->appName = $appName;
        $appModule = sprintf('%s\\Module\\AppModule', $appName);
        assert(class_exists($appModule));
        $this->meta = new Meta($appName);
        /** @psalm-suppress all */
        $injector = new Injector(new $appModule($this->meta, new AppMetaModule($this->meta)));
        assert($injector instanceof InjectorInterface);
        $reader = $injector->getInstance(Reader::class);
        assert($reader instanceof Reader);
        try {
            /** @var string responseSchemaDir $responseSchemaDir */
            $responseSchemaDir = $injector->getInstance('', 'json_schema_dir');
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
            // @codeCoverageIgnoreStart
            $responseSchemaDir = '';
            // @codeCoverageIgnoreEnd
        }

        $this->responseSchemaDir = $responseSchemaDir;
        try {
            $requestSchemaDir = $injector->getInstance('', 'json_validate_dir');
            // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
            $requestSchemaDir = '';
            // @codeCoverageIgnoreEnd
        }

        assert(is_string($requestSchemaDir));
        /** @var ArrayObject<string, string> $modelRepository */
        $modelRepository = new ArrayObject();
        $this->modelRepository = $modelRepository;
        $this->docClass = new DocClass($reader, $requestSchemaDir, $this->responseSchemaDir, $this->modelRepository);
        $map = $this->getRouterMap($injector);
        // @codeCoverageIgnoreStart
        if (! is_iterable($map)) {
            return;
        }

        // @codeCoverageIgnoreEnd

        foreach ($map as $route) {
            assert($route instanceof Route);
            $this->routes[$route->name] = $route->path;
        }
    }

    public function __invoke(): void
    {
        if ($this->config->format === 'md') {
            $this->dumpMd();

            return;
        }

        $this->dumpHtml();
    }

    public function dumpMd(): void
    {
        $docDir = $this->config->docDir;
        $scheme = $this->config->scheme;
        $alpsFile = $this->config->alps;
        $this->mkDir($docDir);
        $genMarkDown = $this->getGenMarkdown($docDir, $scheme, 'md', $alpsFile);
        foreach ($genMarkDown as $file => [$markdown]) {
            $this->filePutContents($file . '.md', $markdown);
        }
    }

    public function dumpHtml(): void
    {
        $docDir = $this->config->docDir;
        $scheme = $this->config->scheme;
        $alpsFile = $this->config->alps;
        $this->mkDir($docDir);
        $genMarkDown = $this->getGenMarkdown($docDir, $scheme, 'html', $alpsFile);
        $mdToHtml = new MdToHtml();
        foreach ($genMarkDown as $file => [$markdown, $path]) {
            $title = sprintf('%s %s', $this->appName, $path);
            $html = $mdToHtml($title, $markdown);
            $this->filePutContents($file . '.html', $html);
        }
    }

    private function filePutContents(string $file, string $contentes): void
    {
        touch($file);
        if (! is_writable($file)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException($file);
            // @codeCoverageIgnoreEnd
        }

        file_put_contents($file, $contentes);
    }

    private function mkDir(string $docDir): void
    {
        $dir = sprintf('%s/%s/paths', $this->dir, $docDir);
        if (is_dir($dir)) {
            return;
        }

        // @codeCoverageIgnoreStart
        mkdir($dir, 777, true);
    }

        // @codeCoverageIgnoreEnd

    /**
     * @return Generator<string, array{0: string, 1:string}>
     */
    private function getGenMarkdown(string $docDir, string $scheme, string $ext, string $alpsFile): Generator
    {
        /** @var ArrayObject<string, string> $nullDictionary */
        $nullDictionary = new ArrayObject();
        $alpsFilePath = $alpsFile ? sprintf('%s/%s', $this->dir, $alpsFile) : '';
        $semanticDictionary = $alpsFilePath ? $this->registerAlpsProfile($alpsFilePath) : $nullDictionary;
        $generator = $this->meta->getGenerator($scheme);
        $paths = [];
        foreach ($generator as $meta) {
            $path = $this->routes[$meta->uriPath] ?? $meta->uriPath;
            $markdown = ($this->docClass)($path, new ReflectionClass($meta->class), $semanticDictionary, $ext);
            $file = sprintf('%s/%s/paths/%s', $this->dir, $docDir, substr($meta->uriPath, 1));
            $paths[$path] = substr($meta->uriPath, 1);

            yield $file => [$markdown, $path];
        }

        if ($this->responseSchemaDir) {
            $this->copySchemas($docDir);
        }

        /** @var list<string> $objects */
        $objects = array_unique((array) $this->modelRepository);

        $index = (string) new Index($this->config, $paths, $objects, $ext);

        yield sprintf('%s/%s/index', $this->dir, $docDir) => [$index, ''];
    }

    private function copySchemas(string $docDir): void
    {
        $outputDir = sprintf('%s/%s/schema', $this->dir, $docDir);
        ! is_dir($outputDir) && ! mkdir($outputDir) && ! is_dir($outputDir);
        $this->copySchema($this->responseSchemaDir, $outputDir);
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

    /**
     * @psalm-return Map
     * @phpstan-return Map<string, Route>
     */
    private function getRouterMap(InjectorInterface $injector): ?Map // @phpstan-ignore-line
    {
        try {
            /** @var RouterContainer */
            $routerContainer = $injector->getInstance(RouterContainer::class);

            return $routerContainer->getMap();
        // @codeCoverageIgnoreStart
        } catch (Unbound $e) {
            return null;
        }
    }
}
