<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ArrayObject;
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\Link;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionMethod;
use Stringable;

use function assert;
use function implode;
use function is_string;
use function sprintf;
use function strtoupper;
use function substr;

use const PHP_EOL;

final class DocMethod implements Stringable
{
    /** @var string */
    private $title = '';

    /** @var string */
    private $description = '';

    /** @var string */
    private $httpMethod;

    /** @var array<int, DocParam> */
    private $params;

    /**
     * @param ArrayObject<string, string> $semanticDictionary
     */
    public function __construct(
        private readonly ReflectionMethod $method,
        ?Schema $request,
        private readonly ?Schema $response,
        private readonly ArrayObject $semanticDictionary,
        private readonly string $ext,
    ) {
        $this->httpMethod = substr($this->method->name, 2);
        $factory = DocBlockFactory::createInstance();
        $docComment = $this->method->getDocComment();
        if (is_string($docComment)) {
            $docblock = $factory->create($docComment);
            $this->title = $docblock->getSummary();
            $this->description = (string) $docblock->getDescription();
            $tagParams = $this->getTagParams($docblock);
        }

        /** @var ?array<string, TagParam> $tagParams */   // phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.NoAssignment
        $tagParams ??= null;
        $this->params = $this->getDocParams($this->method, $tagParams, $request, $semanticDictionary);
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
            $hasTagParam = (bool) $tagParams && isset($tagParams[$name]);
            $tagParam = $hasTagParam ? $tagParams[$name] : new TagParam('', '');
            $prop = $request->props[$name] ?? null;
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

    #[\Override]
    public function __toString(): string
    {
        $title = $this->title;
        $description = $this->description;
        $alpsSection = $this->getAlpsSection();
        $format = <<<EOT
## %s
{$this->lineString($title)}{$this->lineString($description)}{$alpsSection}

### Request

%s

### Response

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
            return '_No parameters required_';
        }

        return <<<EOT
| Name | Type | Description | Default | Required | Constraints | Example |
|------|------|-------------|---------|----------|-------------|---------|
{$table}
EOT;
    }

    private function toStringResponse(): string
    {
        if ($this->response === null) {
            return '_Not available_';
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

| Name | Type | Description | Required | Constraints | Example |
|------|------|-------------|----------|-------------|---------|
{$rows}
EOT;
    }

    private function lineString(?string $string): string
    {
        return ! (bool) $string ? '' : $string . PHP_EOL . PHP_EOL;
    }

    private function getEmbeds(): string
    {
        $attributes = $this->method->getAttributes(Embed::class);
        $items = [];
        foreach ($attributes as $attribute) {
            $embed = $attribute->newInstance();
            $items[] = sprintf('| %s | %s |', $embed->rel, (string) new Src($embed->src, $this->ext));
        }

        $rows = implode(PHP_EOL, $items);

        if (! $rows) {
            return '';
        }

        return <<<EOT

#### Embedded Resources

| Relation | Source |
|----------|--------|
{$rows}
EOT;
    }

    private function getLinks(): string
    {
        $attributes = $this->method->getAttributes(Link::class);
        $items = [];
        foreach ($attributes as $attribute) {
            $link = $attribute->newInstance();
            $items[] = sprintf('| %s | %s |', $link->rel, (string) new Src($link->href, $this->ext));
        }

        $rows = implode(PHP_EOL, $items);

        if (! $rows) {
            return '';
        }

        return <<<EOT

#### Links

| Relation | URL |
|----------|-----|
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
            $examples .= $example . PHP_EOL;
        }

        return <<<EOT

#### Example

```json
{$examples}```
EOT;
    }

    private function getAlpsSection(): string
    {
        $attributes = $this->method->getAttributes(Alps::class);
        if ($attributes === []) {
            return '';
        }

        $ids = [];
        foreach ($attributes as $attribute) {
            $alps = $attribute->newInstance();
            $ids[] = $alps->id;
        }

        $alpsIds = implode(', ', $ids);
        $semanticInfo = $this->getAlpsSemanticInfo($ids);

        return sprintf("**ALPS**: `%s`%s\n\n", $alpsIds, $semanticInfo);
    }

    /**
     * @param array<string> $ids
     */
    private function getAlpsSemanticInfo(array $ids): string
    {
        $info = [];
        foreach ($ids as $id) {
            if (isset($this->semanticDictionary[$id])) {
                $info[] = $this->semanticDictionary[$id];
            }
        }

        return $info !== [] ? ' - ' . implode(', ', $info) : '';
    }
}
