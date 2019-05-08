<?php
namespace BEAR\ApiDoc;

use Koriym\Alps\AbstractAlps;
use Koriym\Alps\Alps;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use function implode;
use function sprintf;


final class ParamDescExtension extends AbstractExtension
{
    /**
     * @var AbstractAlps
     */
    private $alps;

    public function __construct(AbstractAlps $alps)
    {
        $this->alps = $alps;
    }

    public function getFilters() : array
    {
        return [
            new TwigFilter('param_desc', [$this, 'param_desc'])
        ];
    }

    public function param_desc(string $description = null, string $semanticName = null, $prop = null, $schema = null) : string
    {
        if ($description) {
            return $description;
        }
        if ($prop instanceof \stdClass) {
            $desc = $this->getDescription($prop, 'title');
            if ($desc) {
                return $desc;
            }
        }
        $name = lcfirst(strtr(ucwords(strtr((string) $semanticName, ['_' => ' '])), [' ' => '']));
        $semantic = $this->alps->semantics[$name] ?? null;
        if ($semantic) {
            if ($semantic->def) {
                return sprintf('[%s](%s)', $semanticName, $semantic->def);
            }

            return $this->getDescription($semantic, 'name');
        }
        if ($semanticName[0] !== '_' && $this->alps instanceof Alps) {
            $this->errorLog((string) $semanticName, $schema);
        }

        return '';
    }

    private function getDescription(\stdClass $semantic, string $name) : string
    {
        $names = [];
        if ($semantic->{$name}) {
            $names[] = $semantic->{$name};
        }
        if ($semantic->doc->value) {
            $names[] = $semantic->doc->value;
        }

        return implode(', ', $names);
    }

    private function errorLog(string $semanticName, $schema) : void
    {
        $id = $schema['$id'] ?? '';
        $msg = $id ? sprintf('Missing semantic doc [%s] in [%s]', $semanticName, $id) : sprintf('Missing semantic doc [$%s]', $semanticName);
        error_log($msg);
    }
}
