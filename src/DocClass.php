<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\Resource\Annotation\JsonSchema;
use Doctrine\Common\Annotations\Reader;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

use function assert;
use function file_get_contents;
use function implode;
use function in_array;
use function is_file;
use function is_object;
use function json_decode;
use function sprintf;

use const PHP_EOL;

final class DocClass
{
    /** @var ModelRepository */
    public $modelRepository;

    /** @var Reader */
    private $reader;

    /** @var string */
    private $requestSchemaDir;

    /** @var string */
    private $responseSchemaDir;

    /** @var ArrayObject<string, string> */
    private $semanticDictionary;

    public function __construct(
        Reader $reader,
        string $requestSchemaDir,
        string $responseSchemaDir,
        ModelRepository $modelRepository
    ) {
        $this->reader = $reader;
        $this->requestSchemaDir = $requestSchemaDir;
        $this->responseSchemaDir = $responseSchemaDir;
        $this->modelRepository = $modelRepository;
        /** @var ArrayObject<string, string> $nullDictinary */
        $nullDictinary = new ArrayObject();
        $this->semanticDictionary = $nullDictinary;
    }

    /**
     * @param ReflectionClass<object>     $class
     * @param ArrayObject<string, string> $semanticDictionary
     */
    public function __invoke(string $title, string $path, ReflectionClass $class, ArrayObject $semanticDictionary, string $ext): string
    {
        $this->semanticDictionary = $semanticDictionary;
        $docComment = (string) $class->getDocComment();
        [$summary, $description, $links] = (new PhpDoc())($docComment);
        $methods = $class->getMethods();
        $views = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            $isRequestMethod = in_array($name, ['onGet', 'onPut', 'onPost', 'onPatch', 'onDelete']);
            if ($isRequestMethod) {
                $views[] = $this->getMethodView($method, $ext);
            }
        }

        $methodsView = implode(PHP_EOL, $views);

        return <<<EOT
<a href="../index.{$ext}" style="color: black; text-decoration: none;">{$title}</a>

# {$path}
{$summary}{$description}{$links}
{$methodsView}
EOT;
    }

    private function getMethodView(ReflectionMethod $method, string $ext): string
    {
        $schema = $this->reader->getMethodAnnotation($method, JsonSchema::class);
        [$request, $response] = $schema instanceof JsonSchema ? [$this->getSchema($this->requestSchemaDir, $schema->params), $this->getResponseSchema($this->responseSchemaDir, $schema->schema)] : [null, null];

        return (string) new DocMethod($this->reader, $method, $request, $response, $this->semanticDictionary, $ext);
    }

    private function getResponseSchema(string $dir, string $file): ?Schema
    {
        $schemaJson = $this->getSchema($dir, $file);
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (isset($schemaJson->type) && $schemaJson->type === 'object') {
            $this->modelRepository[$schemaJson->title] = $file;
        }

        return $schemaJson;
    }

    private function getSchema(string $dir, string $file): ?Schema
    {
        $schemaFile = sprintf('%s/%s', $dir, $file);
        if (! is_file($schemaFile)) {
            return null;
        }

        $schemaJson = json_decode((string) file_get_contents($schemaFile));
        assert(is_object($schemaJson));
        $fileInfo = new SplFileInfo($schemaFile);

        return new Schema($fileInfo, $schemaJson, $this->semanticDictionary);
    }
}
