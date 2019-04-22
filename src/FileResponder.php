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
use function strtoupper;
use function trigger_error;

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
    public function __construct(string $docDir, string $host = '')
    {
        $this->docDir = $docDir;
        $this->host = $host;
        $this->jsonSaver = new JsonSaver;
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
        $errors = $this->writeRel($apiDoc, $this->rels, $this->docDir, $this->schemaDir);
        $this->copyJson($this->docDir, $this->schemaDir);
        foreach ($errors as $error) {
            trigger_error($error);
        }

        return null;
    }

    public function set(string $index, string $schemaDir, array $uris, string $ext, array $rels)
    {
        $this->index = $index;
        $this->schemaDir = $schemaDir;
        $this->uris = $uris;
        $this->ext = $ext;
        $this->rels = $rels;
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
            $uriDir = $docDir . '/uri';
            $apiDoc->body = (array) $uri + ['uri' => ''];
            $apiDoc->view = null;
            $view = (string) $apiDoc;
            if (! is_dir($uriDir) && ! mkdir($uriDir, 0777, true) && ! is_dir($uriDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $uriDir)); // @codeCoverageIgnore
            }
            file_put_contents(sprintf('%s/%s', $docDir, $uri->filePath), $view);
        }
    }

    /**
     * @return string[]
     */
    private function writeRel(ApiDoc $apiDoc, array $links, string $docDir) : array
    {
        $errors = [];
        foreach ($links as $relMeta) {
            $apiDoc->view = null;
            [$rel, $href, $method] = [$relMeta['rel'], $relMeta['href'], strtoupper($relMeta['method'])];
            if (! isset($this->uris[$href]->doc[$method])) {
                $errors[] = "Link target not exists rel:{$rel} href:{$href} method:{$method} from:{$relMeta['link_from']}";
                continue;
            }
            $targetLink = $this->uris[$href]->doc[$method];
            ($this->jsonSaver)($docDir . '/rels', $rel, (object) $targetLink);
        }

        return $errors;
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
