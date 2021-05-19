<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\Link;
use Doctrine\Common\Annotations\Reader;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionMethod;

use function assert;
use function implode;
use function sprintf;
use function strtoupper;
use function substr;

use const PHP_EOL;

final class DocMethod
{
    /** @var string */
    private $title = '';

    /** @var string */
    private $description = '';

    /** @var string */
    private $httpMethod;

    /** @var array<int, DocParam> */
    private $params;

    /** @var ?Schema */
    private $response;

    /** @var Reader */
    private $reader;

    /** @var ReflectionMethod  */
    private $method;

    /** @var string */
    private $ext;

    /**
     * @param ArrayObject<string, string> $semanticDictionary
     */
    public function __construct(Reader $reader, ReflectionMethod $method, ?Schema $request, ?Schema $response, ArrayObject $semanticDictionary, string $ext)
    {
        $this->method = $method;
        $this->httpMethod = substr($method->name, 2);
        $factory = DocBlockFactory::createInstance();
        $docComment = $method->getDocComment();
        if ($docComment) {
            $docblock = $factory->create($docComment);
            $this->title = $docblock->getSummary();
            $this->description = (string) $docblock->getDescription();
            $tagParams = $this->getTagParams($docblock);
        }

        /** @var  ?array<string, TagParam> $tagParams */
        $tagParams = $tagParams ?? null;
        $this->params = $this->getDocParams($method, $tagParams, $request, $semanticDictionary);
        $this->response = $response;
        $this->reader = $reader;
        $this->ext = $ext;
    }

    /**
     * @param array<string, TagParam>|null $tagParams
     * @param ArrayObject<string, string>  $semanticDictionary
     *
     * @return array<int, DocParam>
     */
    private function getDocParams(ReflectionMethod $method, ?array $tagParams, ?Schema $request, ArrayObject $semanticDictionary): array
    {
        $parameters = $method->getParameters();
        $docParams = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $hasTagParam = $tagParams && isset($tagParams[$name]);
            $tagParam = $hasTagParam ? $tagParams[$name] : new TagParam('', '');
            $prop = $request->props[$name] ?? null; // @phpstan-ignore-line
            $docParams[] = new DocParam($parameter, $tagParam, $prop, $semanticDictionary);
        }

        return $docParams;
    }

    /**
     * @return array<string, TagParam>
     */
    private function getTagParams(DocBlock $docblock): array
    {
        $tagParams = [];
        $params = $docblock->getTagsByName('param');
        /** @var array<DocBlock\Tags\Param> $params */
        foreach ($params as $param) {
            $name = (string) $param->getVariableName();
            $tagParams[$name] = new TagParam((string) $param->getType(), (string) $param->getDescription());
        }

        return $tagParams;
    }

    public function __toString(): string
    {
        $title = $this->title;
        $description = $this->description;
        $format = <<<EOT
## %s
{$this->lineString($title)}{$this->lineString($description)}

**Request**

%s

**Response**

%s
EOT;

        return sprintf(
            $format,
            strtoupper($this->httpMethod),
            $this->toStringRequest(),
            $this->toStringResponse()
        );
    }

    private function toStringRequest(): string
    {
        $table = '';
        foreach ($this->params as $param) {
            $table .= (string) $param . PHP_EOL;
        }

        return $this->getRequestBody($table);
    }

    private function getRequestBody(string $table): string
    {
        if ($table === '') {
            return '(No parameters required)';
        }

        return <<<EOT
| Name  | Type  | Description | Default | Required | Constraints | Example |
|-------|-------|-------------|---------|----------|-------------|---------| 
{$table}
EOT;
    }

    private function toStringResponse(): string
    {
        if ($this->response === null) {
            return '(n/a)';
        }

        if ($this->response->type === 'array') {
            return $this->response->toStringTypeArray();
        }

        $rows = '';
        foreach ($this->response->props as $prop) {
            $row = (string) $prop;
            if ($row === '') {
                continue;
            }

            $rows .= $row . PHP_EOL;
        }

        $object =  $this->getObjectTable($this->response->title(), $rows);

        return $object . $this->getEmbeds() . $this->getLinks() . $this->getExample();
    }

    private function getObjectTable(string $responseTitle, string $rows): string
    {
        return <<<EOT
{$responseTitle}

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|------------|---------| 
{$rows}
EOT;
    }

    private function lineString(?string $string): string
    {
        return ! $string ? '' : $string . PHP_EOL . PHP_EOL;
    }

    private function getEmbeds(): string
    {
        $annotations = $this->reader->getMethodAnnotations($this->method);
        $items = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Embed) {
                $items[] = sprintf('| %s | %s |', $annotation->rel, (string) new Src($annotation->src, $this->ext));
            }
        }

        $rows = implode(PHP_EOL, $items);

        if (! $rows) {
            return '';
        }

        return <<<EOT

#### Embedded

| rel | src |
|-----|-----|
{$rows}
EOT;
    }

    private function getLinks(): string
    {
        $annotations = $this->reader->getMethodAnnotations($this->method);
        $items = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Link) {
                $items[] = sprintf('| %s | %s |', $annotation->rel, (string) new Src($annotation->href, $this->ext));
            }
        }

        $rows = implode(PHP_EOL, $items);

        if (! $rows) {
            return '';
        }

        return <<<EOT


#### Links

| rel | href |
|-----|-----|
{$rows}
EOT;
    }

    private function getExample(): string
    {
        assert($this->response instanceof Schema);
        if ($this->response->examples === []) {
            return '';
        }

        $examples = '';
        foreach ($this->response->examples as $example) {
            $examples .= sprintf('<code>%s</code>' . PHP_EOL, $example);
        }

        return <<<EOT

#### Example

<pre>
{$examples}
</pre>
EOT;
    }
}
