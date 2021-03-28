<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\Resource\Annotation\JsonSchema;
use Doctrine\Common\Annotations\Reader;
use phpDocumentor\Reflection\DocBlock\Tags\Link;
use phpDocumentor\Reflection\DocBlockFactory;
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

/**
 * @psalm-pure
 */
final class DocClass
{
    /** @var Reader */
    private $reader;

    /** @var string */
    private $requestSchemaDir;

    /** @var string */
    private $responseSchemaDir;

    /** @var ArrayObject */
    private $modelRepository;

    public function __construct(Reader $reader, string $requestSchemaDir, string $responseSchemaDir, ArrayObject $modelRepository)
    {
        $this->reader = $reader;
        $this->requestSchemaDir = $requestSchemaDir;
        $this->responseSchemaDir = $responseSchemaDir;
        $this->modelRepository = $modelRepository;
    }

    public function __invoke(string $path, ReflectionClass $class): string
    {
        [$summary, $description, $links] = $this->classTag($class);
        $methods = $class->getMethods();
        $views = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            $isRequestMethod = in_array($name, ['onGet', 'onPut', 'onPost', 'onPatch', 'onDelete']);
            if ($isRequestMethod) {
                $views[] = $this->getMethodView($method);
            }
        }

        $methodsView = implode(PHP_EOL, $views);

        return <<<EOT
# {$path}
{$summary}{$description}{$links}
{$methodsView}
EOT;
    }

    /**
     * @return array{0: string, 1:string, 2:string}
     */
    private function classTag(ReflectionClass $class): array
    {
        $factory = DocBlockFactory::createInstance();
        $docComment = $class->getDocComment();
        if (! $docComment) {
            return ['', '', ''];
        }

        $docblock = $factory->create($docComment);
        $summary = $docblock->getSummary() . PHP_EOL . PHP_EOL;
        $description = (string) $docblock->getDescription() . PHP_EOL . PHP_EOL;
        /** @var list<Link> $tagLinks */
        $tagLinks = $docblock->getTagsByName('link');
        $links = (string) new TagLinks($tagLinks) . PHP_EOL . PHP_EOL;

        return [$summary, $description, $links];
    }

    private function getMethodView(ReflectionMethod $method): string
    {
        $schema = $this->reader->getMethodAnnotation($method, JsonSchema::class);
        [$request, $response] = $schema instanceof JsonSchema ? [$this->getSchema($this->requestSchemaDir, $schema->params), $this->getResponseSchema($this->responseSchemaDir, $schema->schema)] : [null, null];

        return (string) new DocMethod($method, $request, $response);
    }

    private function getResponseSchema(string $dir, string $file): ?Schema
    {
        $schemaJson = $this->getSchema($dir, $file);
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (isset($schemaJson->type) && $schemaJson->type === 'object') {
            $this->modelRepository[] = $schemaJson->title;
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

        return new Schema($fileInfo, $schemaJson);
    }
}
