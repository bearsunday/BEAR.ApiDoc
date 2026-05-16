<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

use function implode;
use function in_array;
use function is_file;
use function sprintf;

use const PHP_EOL;

final class DocClass
{
    /** @var ArrayObject<string, string> */
    private $semanticDictionary;

    private readonly JsonFile $jsonFile;

    public function __construct(
        private readonly string $requestSchemaDir,
        private readonly string $responseSchemaDir,
        public ModelRepository $modelRepository,
        ?JsonFile $jsonFile = null,
    ) {
        /** @var ArrayObject<string, string> $nullDictinary */
        $nullDictinary = new ArrayObject();
        $this->semanticDictionary = $nullDictinary;
        $this->jsonFile = $jsonFile ?? new JsonFile();
    }

    /**
     * @param ReflectionClass<T>          $class
     * @param ArrayObject<string, string> $semanticDictionary
     *
     * @template T of object
     */
    public function __invoke(string $title, string $path, ReflectionClass $class, ArrayObject $semanticDictionary, string $ext): string
    {
        $this->semanticDictionary = $semanticDictionary;
        $docComment = (string) $class->getDocComment();
        [$summary, $description, $links] = (new PhpDoc())($docComment);
        $alpsSection = $this->getAlpsSection($class);
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
{$alpsSection}{$summary}{$description}{$links}
{$methodsView}
EOT;
    }

    /**
     * @param ReflectionClass<T> $class
     *
     * @template T of object
     */
    private function getAlpsSection(ReflectionClass $class): string
    {
        $attributes = $class->getAttributes(Alps::class);
        if ($attributes === []) {
            return '';
        }

        $ids = [];
        foreach ($attributes as $attribute) {
            $alps = $attribute->newInstance();
            $ids[] = $alps->id;
        }

        $alpsIds = implode(', ', $ids);
        $semanticInfo = $this->getSemanticInfo($ids);

        return sprintf("**ALPS**: `%s`%s\n\n", $alpsIds, $semanticInfo);
    }

    /** @param array<string> $ids */
    private function getSemanticInfo(array $ids): string
    {
        $info = [];
        foreach ($ids as $id) {
            if (isset($this->semanticDictionary[$id])) {
                $info[] = $this->semanticDictionary[$id];
            }
        }

        return $info !== [] ? ' - ' . implode(', ', $info) : '';
    }

    private function getMethodView(ReflectionMethod $method, string $ext): string
    {
        $attributes = $method->getAttributes(JsonSchema::class);
        $schema = isset($attributes[0]) ? $attributes[0]->newInstance() : null;
        [$request, $response] = $schema instanceof JsonSchema ? [$this->getSchema($this->requestSchemaDir, $schema->params), $this->getResponseSchema($this->responseSchemaDir, $schema->schema)] : [null, null];

        return (string) new DocMethod($method, $request, $response, $this->semanticDictionary, $ext);
    }

    private function getResponseSchema(string $dir, string $file): ?Schema
    {
        $schemaJson = $this->getSchema($dir, $file);
        if ($schemaJson instanceof \BEAR\ApiDoc\Schema && $schemaJson->type === 'object') {
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

        $fileInfo = new SplFileInfo($schemaFile);

        return new Schema($fileInfo, $this->jsonFile->object($schemaFile), $this->semanticDictionary);
    }
}
