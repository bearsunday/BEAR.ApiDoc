<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionMethod;

use function sprintf;
use function strtoupper;
use function substr;

use const PHP_EOL;

final class DocMethod
{
    /**
     * @var string
     * @readonly
     */
    private $title;

    /**
     * @var string
     * @readonly
     */
    private $description;

    /** @var string */
    private $httpMethod;

    /**
     * @var array<int, DocParam>
     * @readonly
     */
    private $params = [];

    /**
     * @var Schema
     * @readonly
     */
    private $response;

    /**
     * Return docBloc and parameter metas of method
     */
    public function __construct(ReflectionMethod $method, ?Schema $request, ?Schema $response)
    {
        $this->httpMethod = substr($method->name, 2);
        $factory = DocBlockFactory::createInstance();
        $docComment = $method->getDocComment();
        if ($docComment) {
            $docblock = $factory->create($docComment);
            $this->title = $docblock->getSummary();
            $this->description = (string) $docblock->getDescription();
            $tagParams = $this->getTagParams($docblock);
        }

        $tagParams = $tagParams ?? null;
        $this->params = $this->getDocParamas($method, $tagParams, $request);
        $this->response = $response;
    }

    /**
     * @param array<string, TagParam>|null $tagParams
     *
     * @return array<int, DocParam>
     */
    private function getDocParamas(ReflectionMethod $method, ?array $tagParams, ?Schema $request): array
    {
        $parameters = $method->getParameters();
        $docParams = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $hasTagParam = $tagParams && isset($tagParams[$name]);
            $tagParam = $hasTagParam ? $tagParams[$name] : new TagParam('', ''); // @phpstan-ignore-line
            $prop = $request->props[$name] ?? null;
            $docParams[] = new DocParam($parameter, $tagParam, $prop);
        }

        return $docParams;
    }

    /**
     * @return array<TagParam>
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

    public function __toString()
    {
        $title = $this->title;
        $description = $this->description;
        $format = <<<EOT
## %s
{$this->lineString($title)}{$this->lineString($description)}

### Request
%s

### Response
%s       
EOT;

        return sprintf($format, strtoupper($this->httpMethod), $this->toStringRequest(), $this->toStringResponse());
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
| Name  | Type  | Description | Default | Example |
|-------|-------|-------------|---------|---------| 
{$table}        
EOT;
    }

    private function toStringResponse(): string
    {
        if ($this->response === null) {
            return '(No response body)';
        }

        if ($this->response->type === 'array') {
            return $this->response->toStringTypeArray();
        }

        $rows = '';
        foreach ($this->response->props as $prop) {
            $rows .= (string) $prop . PHP_EOL;
        }

        return $this->getObjectTable($this->response->title(), $rows);
    }

    private function getObjectTable(string $responseTitle, string $rows)
    {
        return <<<EOT
{$responseTitle}

| Name  | Type  | Description | Required | Constraint | Example |
|-------|-------|-------------|----------|-----------|---------| 
{$rows}        
EOT;
    }

    private function lineString(?string $string): string
    {
        return ! $string ? '' : $string . PHP_EOL . PHP_EOL;
    }
}
