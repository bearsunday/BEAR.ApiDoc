<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use AlpsAsd\AlpsProfile\ProfileDictionary;
use ArrayObject;
use BEAR\ApiDoc\Exception\AlpsFileNotFoundException;
use BEAR\ApiDoc\Exception\NotWritableException;
use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use SplFileInfo;

use function assert;
use function chmod;
use function copy;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_string;
use function json_encode;
use function mkdir;
use function realpath;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function substr;

use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

final readonly class ApiDoc
{
    private const OBJECT_GRAPH_CSS_URL = 'https://cdn.jsdelivr.net/gh/bearsunday/BEAR.ApiDoc@557ff95f77c8a090b137eaaa05570c42ada88cf9/docs/assets/bindings-object-graph.css';
    private const OBJECT_GRAPH_JS_URL = 'https://cdn.jsdelivr.net/gh/bearsunday/BEAR.ApiDoc@62584731f761c01030d1c546f7fba77b289bf6ee/docs/assets/bindings-object-graph.js';
    private const VIZ_JS_URL = 'https://cdn.jsdelivr.net/npm/@viz-js/viz@3.28.0/dist/viz-global.js';

    /** @SuppressWarnings("PHPMD.BooleanArgumentFlag") */
    public function __construct(
        private bool $inlineCss = false,
    ) {
    }

    public function __invoke(string $configFile): string
    {
        $config = new Config($configFile);
        $this->mkDir($config->docDir);
        $fakeDataExampleResolver = new FakeDataExampleResolver($config->fakeDataDir, $config->docDir);
        $docClass = new DocClass(
            $config->requestSchemaDir,
            $config->responseSchemaDir,
            new ModelRepository(),
            null,
            $fakeDataExampleResolver,
        );

        $outputFiles = [];
        foreach ($config->formats as $format) {
            $this->dumpFormat($config, $docClass, $format, $fakeDataExampleResolver);
            foreach ($this->outputFilesForFormat($format) as $outputFile) {
                $outputFiles[] = $outputFile;
            }
        }

        return sprintf('ApiDoc generated. %s/%s', (string) realpath($config->docDir), implode(', ', $outputFiles));
    }

    /** @return list<string> */
    private function outputFilesForFormat(string $format): array
    {
        return match ($format) {
            'audit' => ['audit.md', 'audit.html'],
            'bindings' => ['bindings.html'],
            'openapi' => ['openapi.json'],
            'md' => ['index.md', 'terms.md'],
            'llms' => ['llms.txt'],
            'terms' => ['terms.md'],
            default => ['index.html', 'terms.html'],
        };
    }

    private function dumpFormat(Config $config, DocClass $docClass, string $format, FakeDataExampleResolver $fakeDataExampleResolver): void
    {
        $this->mkDir($config->docDir);

        if ($format === 'md') {
            $this->dumpMd($config, $docClass);

            return;
        }

        if ($format === 'openapi') {
            $this->dumpOpenApi($config, $fakeDataExampleResolver);

            return;
        }

        if ($format === 'llms') {
            $this->dumpLlms($config);

            return;
        }

        if ($format === 'audit') {
            $this->dumpAudit($config);

            return;
        }

        if ($format === 'terms') {
            $this->dumpTerms($config);

            return;
        }

        if ($format === 'bindings') {
            $this->dumpBindings($config);

            return;
        }

        $this->dumpHtml($config, $docClass, $fakeDataExampleResolver);
    }

    public function dumpMd(Config $config, DocClass $docClass): void
    {
        $genMarkDown = $this->getGenMarkdown($config, 'md', $docClass);
        foreach ($genMarkDown as $file => [$markdown]) {
            $dir = dirname($file);
            // @codeCoverageIgnoreStart
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            // @codeCoverageIgnoreEnd

            $this->filePutContents($file . '.md', $markdown);
        }

        $this->dumpTermsMarkdown($config);
    }

    public function dumpHtml(Config $config, DocClass $docClass, ?FakeDataExampleResolver $fakeDataExampleResolver = null): void
    {
        unset($docClass);

        /** @var ArrayObject<string, string> $nullDictionary */
        $nullDictionary = new ArrayObject();
        $semanticDictionary = $config->alps !== '' && $config->alps !== '0' ? $this->registerAlpsProfile($config->alps) : $nullDictionary;

        $generator = new HtmlGenerator(
            $config,
            $config->requestSchemaDir,
            $config->responseSchemaDir,
            $semanticDictionary,
            $this->inlineCss,
            null,
            $fakeDataExampleResolver,
        );
        $html = $generator->generate();
        $outputFile = sprintf('%s/index.html', $config->docDir);
        $this->filePutContents($outputFile, $html);
        $this->dumpTermsHtml($config);

        if ($config->responseSchemaDir !== '' && $config->responseSchemaDir !== '0') {
            $this->copySchemas($config);
        }
    }

    private function filePutContents(string $file, string $contents): void
    {
        if (file_put_contents($file, $contents) === false) {
            // @codeCoverageIgnoreStart
            throw new NotWritableException($file);
            // @codeCoverageIgnoreEnd
        }
    }

    private function mkDir(string $docDir): void
    {
        $dir = sprintf('%s/paths', $docDir);
        if (is_dir($dir)) {
            return;
        }

        // @codeCoverageIgnoreStart
        if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new NotWritableException($dir);
        }

        chmod(dirname($dir), 0777);
        chmod($dir, 0777);
        // @codeCoverageIgnoreEnd
    }

    /** @return Generator<string, array{0: string, 1:string}> */
    private function getGenMarkdown(Config $config, string $ext, DocClass $docClass): Generator
    {
        /** @var ArrayObject<string, string> $nullDictionary */
        $nullDictionary = new ArrayObject();
        $semanticDictionary = $config->alps !== '' && $config->alps !== '0' ? $this->registerAlpsProfile($config->alps) : $nullDictionary;
        $paths = [];
        foreach ($config->resourceFiles as $meta) {
            $path = $config->routes[$meta->uriPath] ?? $meta->uriPath;
            $markdown = $docClass($config->title, $path, new ReflectionClass($meta->class), $semanticDictionary, $ext);
            $file = sprintf('%s/paths/%s', $config->docDir, substr($meta->uriPath, 1));
            $paths[$path] = substr($meta->uriPath, 1);

            yield $file => [$markdown, $path];
        }

        if ($config->responseSchemaDir !== '' && $config->responseSchemaDir !== '0') {
            $this->copySchemas($config);
        }

        $index = (string) new Index($config, $paths, $docClass->modelRepository, $ext);

        yield sprintf('%s/index', $config->docDir) => [$index, ''];
    }

    private function copySchemas(Config $config): void
    {
        $outputDir = sprintf('%s/schemas', $config->docDir);
        if (! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
            // @codeCoverageIgnoreStart
            throw new NotWritableException($outputDir);
            // @codeCoverageIgnoreEnd
        }

        $this->copySchema($config->responseSchemaDir, $outputDir);

        // Generate schema index
        $indexHtml = (string) new SchemaIndex($outputDir);
        $this->filePutContents($outputDir . '/index.html', $indexHtml);
    }

    /** @return ArrayObject<string, string> */
    private function registerAlpsProfile(string $file): ArrayObject
    {
        if (! file_exists($file)) {
            throw new AlpsFileNotFoundException($file);
        }

        return ProfileDictionary::fromFile($file)->toArrayObject();
    }

    private function copySchema(string $inputDir, string $outputDir): void
    {
        foreach (new RecursiveDirectoryIterator($inputDir, FilesystemIterator::SKIP_DOTS) as $file) {
            assert($file instanceof SplFileInfo);
            $this->doCopy($file, $outputDir);
        }
    }

    private function doCopy(SplFileInfo $file, string $outputDir): void
    {
        $fileName = $file->getFilename();
        $destination = sprintf('%s/%s', $outputDir, $fileName);
        $path = (string) $file;
        if (is_dir($path)) {
            return;
        }

        // @codeCoverageIgnoreStart
        if (! copy($path, $destination)) {
            throw new NotWritableException($destination);
        }

        // @codeCoverageIgnoreEnd
    }

    private function dumpOpenApi(Config $config, ?FakeDataExampleResolver $fakeDataExampleResolver = null): void
    {
        $generator = new OpenApiGenerator(
            $config,
            $config->requestSchemaDir,
            $config->responseSchemaDir,
            null,
            $fakeDataExampleResolver,
        );
        $openApiJson = $generator->generate();
        $outputFile = sprintf('%s/openapi.json', $config->docDir);
        $this->filePutContents($outputFile, $openApiJson);
    }

    private function dumpLlms(Config $config): void
    {
        $generator = new AppDocGenerator($config);
        $content = $generator->generate();
        $outputFile = sprintf('%s/llms.txt', $config->docDir);
        $this->filePutContents($outputFile, $content);
    }

    private function dumpAudit(Config $config): void
    {
        $audit = new ApiDocAudit($config);
        $this->filePutContents(sprintf('%s/audit.md', $config->docDir), $audit->generateMarkdown());
        $this->filePutContents(sprintf('%s/audit.html', $config->docDir), $audit->generateHtml());
    }

    private function dumpBindings(Config $config): void
    {
        $markdown = $config->bindingsMarkdown;
        assert($markdown !== '');
        [$composerLock, $lockDir] = $this->readComposerLock($config->appDir);
        $vendorDir = $lockDir !== '' && is_dir($lockDir . '/vendor') ? $lockDir . '/vendor' : '';
        $message = sprintf('%s · %s', $config->appName, $config->context);
        $html = (new BindingsHtmlRenderer())->page($markdown, $composerLock, $message, $vendorDir);
        $dot = $config->objectGraphDot;
        if ($dot !== '') {
            $dotFileName = 'object-graph.dot';
            $this->filePutContents(sprintf('%s/%s', $config->docDir, $dotFileName), $dot);
            $html = $this->injectObjectGraph($html, $dot, $dotFileName);
        }

        $outputFile = sprintf('%s/bindings.html', $config->docDir);
        $this->filePutContents($outputFile, $html);
    }

    /**
     * Embed object-graph DOT under the header; browser renders via @viz-js/viz (ASD pattern).
     * No Graphviz binary at generation time — only object-visual-grapher for DOT text.
     */
    private function injectObjectGraph(string $html, string $dot, string $dotHref): string
    {
        $dotJson = json_encode(
            $dot,
            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $cssUrl = self::OBJECT_GRAPH_CSS_URL;
        $jsUrl = self::OBJECT_GRAPH_JS_URL;
        $vizJsUrl = self::VIZ_JS_URL;
        $section = <<<HTML
<section class="object-graph-section" aria-label="Object graph overview">
<div class="object-graph-label">
<span class="title">Object graph</span>
<span class="hint">Search to focus · drag to pan · click to reset · <a href="{$dotHref}">DOT</a></span>
</div>
<form class="object-graph-search" role="search">
<label for="object-graph-search">Find node</label>
<input type="search" id="object-graph-search" placeholder="Search classes or bindings…" autocomplete="off" disabled>
<output id="object-graph-search-count" for="object-graph-search" aria-live="polite">0 / 0</output>
</form>
<div class="object-graph-frame">
<div class="object-graph" id="object-graph-mount" tabindex="0" aria-label="Object graph">
<p class="object-graph-status">Rendering object graph…</p>
</div>
<div class="object-graph-zoom" role="group" aria-label="Object graph zoom">
<button type="button" id="object-graph-zoom-in" aria-label="Zoom in" title="Zoom in" disabled>
<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6"></circle><path d="M14.5 14.5 20 20M10 7v6M7 10h6"></path></svg>
</button>
<button type="button" id="object-graph-zoom-out" aria-label="Zoom out" title="Zoom out" disabled>
<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6"></circle><path d="M14.5 14.5 20 20M7 10h6"></path></svg>
</button>
</div>
</div>
<script type="application/json" id="object-graph-dot">{$dotJson}</script>
</section>
HTML;
        $assets = <<<HTML
<script src="{$vizJsUrl}" defer></script>
<script src="{$jsUrl}" defer></script>
HTML;
        $html = str_replace('</head>', "<link rel=\"stylesheet\" href=\"{$cssUrl}\">\n</head>", $html);
        $html = str_replace('</body>', $assets . "\n</body>", $html);

        // Cover thumbnail: immediately under the page header, above stats/PROVENANCE.
        $marker = '</header>';
        $pos = strpos($html, $marker);
        if ($pos !== false) {
            $insertAt = $pos + strlen($marker);

            return substr($html, 0, $insertAt) . "\n" . $section . substr($html, $insertAt);
        }

        $scriptPos = strpos($html, '<script src=');
        if ($scriptPos === false) {
            return $html . $section;
        }

        return substr($html, 0, $scriptPos) . $section . "\n" . substr($html, $scriptPos);
    }

    /**
     * Load composer.lock for class → source links in bindings.html.
     *
     * Looks in $appDir first, then walks parent directories (monorepo / package-as-appDir
     * layouts). Without a lock, BindingsHtml emits no #srcmap and FQCNs stay plain text.
     *
     * @return array{0: string, 1: string} [lock contents, directory containing the lock]
     */
    private function readComposerLock(string $appDir): array
    {
        $dir = $appDir;
        while (true) {
            $lockFile = $dir . '/composer.lock';
            if (file_exists($lockFile)) {
                $contents = file_get_contents($lockFile);

                return is_string($contents) ? [$contents, $dir] : ['', ''];
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return ['', ''];
            }

            $dir = $parent;
        }
    }

    private function dumpTerms(Config $config): void
    {
        $this->dumpTermsMarkdown($config);
    }

    private function dumpTermsMarkdown(Config $config): void
    {
        $outputFile = sprintf('%s/terms.md', $config->docDir);
        $this->filePutContents($outputFile, (new TermUsageIndex($config))->generateMarkdown());
    }

    private function dumpTermsHtml(Config $config): void
    {
        $outputFile = sprintf('%s/terms.html', $config->docDir);
        $this->filePutContents($outputFile, (new TermUsageIndex($config))->generateHtml());
    }
}
