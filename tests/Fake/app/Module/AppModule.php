<?php
namespace FakeVendor\FakeProject\Module;

use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\Resource\Module\JsonSchemaModule;
use function dirname;
use function var_dump;

class AppModule extends AbstractAppModule
{
    public function configure()
    {
        $appDir = dirname(__DIR__, 1);
        $this->install(new JsonSchemaModule(
                $appDir . '/var/json_schema',
                $appDir . '/var/json_schema')
        );
        $this->install(new PackageModule);
    }
}