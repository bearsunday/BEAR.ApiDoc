<?php
namespace BEAR\ApiDoc;

use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use LogicException;
use Ray\Di\Di\Named;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
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
     * @Named("docDir=api_doc_dir,host=json_schema_host")
     */
    public function __construct(string $docDir, string $host = '')
    {
        $this->docDir = $docDir;
        $this->host = $host;
    }

    public function __invoke(ResourceObject $ro, array $server)
    {
        if (! $ro instanceof ApiDoc) {
            throw new LogicException; // @codeCoverageIgnore
        }
        $this->writeIndex($this->index, $this->docDir);
        $this->writeRel($ro, $ro->body['links'], $this->docDir, $this->schemaDir);
    }

    public function set(string $index, string $schemaDir)
    {
        $this->index = $index;
        $this->schemaDir = $schemaDir;
    }

    public function writeIndex(string $index, string $docDir)
    {
        if (! is_dir($docDir) && ! mkdir($docDir, 0777, true) && ! is_dir($docDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $docDir)); // @codeCoverageIgnore
        }
        file_put_contents($docDir . '/index.html', $index);
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
            $json = json_decode(file_get_contents($jsonFile));
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
