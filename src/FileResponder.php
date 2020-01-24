<?php

namespace BEAR\ApiDoc;

use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use LogicException;
use Ray\Di\Di\Named;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;

final class FileResponder implements TransferInterface
{
    /**
     * @var string
     */
    private $docDir;

    /**
     * @var array
     */
    private $index;

    /**
     * @var string
     */
    private $schemaDir;

    /**
     * @var string
     */
    private $host;

    /**
     * @var array
     */
    private $uris;

    /**
     * @var string
     */
    private $ext;

    /**
     * @var array
     */
    private $rels;

    /**
     * @var JsonSaver
     */
    private $jsonSaver;

    /**
     * @Named("docDir=api_doc_dir,host=json_schema_host")
     */
    public function __construct(string $docDir, string $host, AbstractTemplate $template)
    {
        $this->docDir = $docDir;
        $this->host = $host;
        $this->jsonSaver = new JsonSaver;
        $this->ext = $template->ext;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ResourceObject $apiDoc, array $server)
    {
        unset($server);
        if (! $apiDoc instanceof ApiDoc) {
            throw new LogicException; // @codeCoverageIgnore
        }
        [$rels, $errors] = $this->writeRels($apiDoc, $this->rels, $this->docDir);
        $this->writeIndex($apiDoc, $this->index, $this->docDir, $rels);
        $this->writeUris($apiDoc, $this->uris, $this->docDir);
        $this->copyJson($this->docDir, $this->schemaDir);
        foreach ($errors as $error) {
            error_log($error);
        }

        return null;
    }

    public function set(array $index, string $schemaDir, array $uris, string $ext, array $rels)
    {
        $this->index = $index;
        $this->schemaDir = $schemaDir;
        $this->uris = $uris;
        $this->ext = $ext;
        $this->rels = $rels;
    }

    public function writeIndex(ApiDoc $apiDoc, array $index, string $docDir, array $rels)
    {
        if (! is_dir($docDir) && ! mkdir($docDir, 0777, true) && ! is_dir($docDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $docDir)); // @codeCoverageIgnore
        }
        $apiDoc->body = $index + [
            'rels' => $rels,
            'page' => 'index',
            'ext' => $this->ext
        ];
        $apiDoc->view = null;
        $view = (string) $apiDoc;
        file_put_contents($docDir . '/index.' . $this->ext, $view);
    }

    private function writeUris(ApiDoc $apiDoc, array $uris, string $docDir)
    {
        foreach ($uris as $uri) {
            $uriDir = $docDir . '/uri';
            $apiDoc->body = (array) $uri + [
                'page' => 'uri',
                'ext' => $this->ext
            ];
            $apiDoc->view = null;
            $view = (string) $apiDoc;
            $this->mkdir($uriDir);
            $file = sprintf('%s/uri%s.%s', $docDir, $uri->uriPath, $this->ext);
            $dir = dirname($file);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($file, $view);
        }
    }

    private function writeRels(ApiDoc $apiDoc, array $links, string $docDir) : array
    {
        $errors = $rels = [];
        foreach ($links as $relMeta) {
            $apiDoc->view = null;
            [$rel, $href, $method] = [$relMeta['rel'], $relMeta['href'], strtoupper($relMeta['method'])];
            $length = strpos($href, '?') ?: strlen($href);
            $path = substr($href, 0, $length);
            unset($href);
            if (! isset($this->uris[$path]->doc[$method])) {
                $errors[] = "Missing relation rel:[{$rel}] href:[{$path}] method:[{$method}] from:[{$relMeta['link_from']}]";

                continue;
            }
            // write JSON
            assert(isset($this->uris[$path]->doc[$method]));
            $targetLink = $this->uris[$path]->doc[$method];
            $json = [
                'rel' => $rel,
                'summary' => $targetLink['request'] ?? '',
                'method' => $method,
                'request' => $targetLink['request'] ?? [],
                'response_schema' => $targetLink['schema'] ?? []
            ];
            ($this->jsonSaver)($docDir . '/rels', $rel, (object) $json);
            // write HTML
            $apiDoc->body = $targetLink + [
                'page' => 'rel',
                'relMeta' => $relMeta,
                'ext' => $this->ext
            ];
            $apiDoc->view = null;
            $view = (string) $apiDoc;
            file_put_contents(sprintf('%s/rels/%s.%s', $docDir, $relMeta['rel'], $this->ext), $view);
            $rels[] = $rel;
        }

        return [$rels, $errors];
    }

    private function copyJson(string $docDir, string $schemaDir)
    {
        $destDir = "{$docDir}/schema";
        if (! is_dir($destDir) && ! mkdir($destDir, 0777, true) && ! is_dir($destDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $destDir));
        }
        foreach ($this->files($schemaDir, 'json') as $jsonFile) {
            $dest = str_replace($schemaDir, $destDir, $jsonFile);
            $json = json_decode((string) file_get_contents($jsonFile));
            if (isset($json->id)) {
                $json->id = $this->host . $json->id;
            }
            if (isset($json->{'$id'})) {
                $json->{'$id'} = $this->host . $json->{'$id'};
            }
            $this->mkdir(dirname($dest));
            file_put_contents($dest, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    private function files(string $dir, string $ext) : \Iterator
    {
        return
            new \RegexIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $dir,
                        \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS
                    ),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                ),
                "/^.+\\.{$ext}/",
                \RecursiveRegexIterator::MATCH
            );
    }

    private function mkdir(string $uriDir) : void
    {
        if (! is_dir($uriDir) && ! mkdir($uriDir, 0777, true) && ! is_dir($uriDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $uriDir)); // @codeCoverageIgnore
        }
    }
}
