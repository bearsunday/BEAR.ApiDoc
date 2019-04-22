<?php
namespace BEAR\ApiDoc;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use LogicException;
use Ray\Di\Di\Named;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;

final class FileResponder implements TransferInterface
{
    /**
     * @var string
     */
    private $docDir;

    /**
     * @var string
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
     * @Named("docDir=api_doc_dir,host=json_schema_host")
     */
    public function __construct(string $docDir, string $host = '')
    {
        $this->docDir = $docDir;
        $this->host = $host;
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
        $links = $apiDoc->body['links'];
        $this->writeIndex($this->index, $this->docDir);
        $this->writeUris($apiDoc, $this->uris, $this->docDir);
        $this->writeRel($apiDoc, $links, $this->docDir, $this->schemaDir);

        return null;
    }

    public function set(string $index, string $schemaDir, array $uris, string $ext)
    {
        $this->index = $index;
        $this->schemaDir = $schemaDir;
        $this->uris = $uris;
        $this->ext = $ext;
    }

    public function writeIndex(string $index, string $docDir)
    {
        if (! is_dir($docDir) && ! mkdir($docDir, 0777, true) && ! is_dir($docDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $docDir)); // @codeCoverageIgnore
        }
        file_put_contents($docDir . '/index.html', $index);
    }

    private function writeUris(ApiDoc $apiDoc, array $uris, string $docDir)
    {
        foreach ($uris as $uri) {
            $apiDoc->body = (array) $uri + ['uri' => ''];
            $apiDoc->view = null;
            $view = (string) $apiDoc;
            $uriDir = $docDir . '/uri';
            if (! is_dir($uriDir) && ! mkdir($uriDir, 0777, true) && ! is_dir($uriDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $uriDir)); // @codeCoverageIgnore
            }
            file_put_contents(sprintf('%s/%s', $docDir, $uri->filePath), $view);
        }
    }

    private function writeRel(ApiDoc $apiDoc, array $links, string $docDir, string $schemaDir)
    {
        foreach ($links as $rel => $relMeta) {
            $apiDoc->view = null;
            $ro = $apiDoc->onGet($rel);
            $view = (string) $ro;
            $relsDir = $docDir . '/rels';
            if (! is_dir($relsDir) && ! mkdir($relsDir, 0777, true) && ! is_dir($relsDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $relsDir)); // @codeCoverageIgnore
            }
            file_put_contents("{$relsDir}/{$rel}.html", $view);
        }
        $this->copyJson($docDir, $schemaDir);
    }

    private function copyJson(string $docDir, string $schemaDir)
    {
        $destDir = "{$docDir}/schema";
        if (! is_dir($destDir) && ! mkdir($destDir, 0777, true) && ! is_dir($destDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $destDir));
        }
        foreach (glob($schemaDir . '/*.json') as $jsonFile) {
            $dest = str_replace($schemaDir, $destDir, $jsonFile);
            $json = json_decode((string) file_get_contents($jsonFile));
            if (isset($json->id)) {
                $json->id = $this->host . $json->id;
            }
            if (isset($json->{'$id'})) {
                $json->{'$id'} = $this->host . $json->{'$id'};
            }
            file_put_contents($dest, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}
