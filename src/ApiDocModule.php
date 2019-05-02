<?php
namespace BEAR\ApiDoc;

use Koriym\Alps\AbstractAlps;
use Ray\Di\AbstractModule;

class ApiDocModule extends AbstractModule
{
    /**
     * @var AbstractAlps
     */
    private $alps;

    public function __construct(AbstractAlps $alps, AbstractModule $module = null)
    {
        $this->alps = $alps;
        parent::__construct($module);
    }

    protected function configure()
    {
        $this->bind(AbstractAlps::class)->toInstance($this->alps);
        $this->bind()->annotatedWith('router_container')->toProvider(RouterContainerProvider::class);
    }
}
