<?php
namespace BEAR\ApiDoc;

use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use LogicException;

final class FileResponder implements TransferInterface
{
    /**
     * @var string
     */
    private $docDir;

    private $index;

    private $schemaDir;

    public function __construct(string $docDir)
    {
        $this->docDir = $docDir;
    }

    public function __invoke(ResourceObject $ro, array $server)
    {
        if (! $ro instanceof ApiDoc) {
            throw new LogicException;
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
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $docDir));
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
            $schemaDir = $docDir . '/schema';
            if (! is_dir($relsDir) && ! mkdir($relsDir, 0777, true) && ! is_dir($relsDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $relsDir));
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
        foreach (glob($schemaDir . '/*.json') as $json) {
            $dest = str_replace($schemaDir, $destDir, $json);
            copy($json, $dest);
        }
    }
}
