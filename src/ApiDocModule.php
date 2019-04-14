<?php
namespace BEAR\ApiDoc;

use Ray\Di\AbstractModule;

class ApiDocModule extends AbstractModule
{
    /**
     * @var string
     */
    private $docDir;

    /**
     * @var string
     */
    private $templateClass;

    public function __construct(string $docDir, string $templateClass, self $module = null)
    {
        $this->docDir = $docDir;
        $this->templateClass = $templateClass;
        parent::__construct($module);
    }

    protected function configure()
    {
        $this->bind()->annotatedWith('api_doc_dir')->toInstance($this->docDir);
        $this->bind(AbstractTemplate::class)->to($this->templateClass);
    }
}
