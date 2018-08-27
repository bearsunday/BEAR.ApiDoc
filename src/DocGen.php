<?php
namespace BEAR\ApiDoc;

use BEAR\AppMeta\Meta;
use BEAR\Package\AppInjector;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;

final class DocGen
{
    public function __invoke(string $appName, string $context) : string
    {
        $meta = new Meta($appName, $context);
        $injector = new AppInjector($appName, $context, $meta);
        $apiDoc = $injector->getInstance(ApiStaticDoc::class);
        /* @var \BEAR\ApiDoc\ApiDoc $apiDoc */
        $apiDoc->setRenderer(new class implements RenderInterface {
            public function render(ResourceObject $ro)
            {
                return new NullResourceObject;
            }
        }); // set twig renderer by self
        $docDir = dirname(__DIR__) . '/docs/api';
        $apiDoc->write($docDir);

        return "API Doc is created at {$docDir}" . PHP_EOL;
    }
}
