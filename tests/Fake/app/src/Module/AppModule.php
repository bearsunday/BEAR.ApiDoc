<?php
namespace FakeVendor\FakeProject\Module;

use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\Package\Provide\Router\AuraRouterModule;
use BEAR\Resource\Module\JsonSchemaLinkHeaderModule;
use BEAR\Resource\Module\JsonSchemaModule;
use Doctrine\Common\Annotations\Reader;
use Koriym\Attributes\DualReader;
use Koriym\Attributes\AttributeReader;
use Doctrine\Common\Annotations\AnnotationReader;

class AppModule extends AbstractAppModule
{
    public function configure()
    {
        $appDir = $this->appMeta->appDir;
        $this->install(new AuraRouterModule($appDir . '/src/var/conf/aura.route.php'));
        $this->install(new JsonSchemaModule(
                $appDir . '/src/var/json_schema',
                $appDir . '/src/var/json_schema')
        );
        $this->install(new JsonSchemaLinkHeaderModule('http://example.com/schema/'));
        $this->install(new PackageModule);
        $this->bind(Reader::class)->toInstance(new DualReader(new AnnotationReader(), new AttributeReader()));
    }
}
