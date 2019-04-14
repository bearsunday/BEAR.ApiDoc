<?php
namespace BEAR\ApiDoc;

use Ray\Di\AbstractModule;

class ApiDocModule extends AbstractModule
{
    protected function configure()
    {
        $this->bind()->annotatedWith('router_container')->toProvider(RouterContainerProvider::class);
    }
}
