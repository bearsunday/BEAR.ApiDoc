<?php
namespace BEAR\ApiDoc;

use BEAR\AppMeta\Meta;
use BEAR\Package\AppInjector;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;

final class DocGen
{
    public function __invoke(string $appName, string $appDir, string $docDir, string $cotext = 'app') : string
    {
        $meta = new Meta($appName, $cotext);
        $injector = new AppInjector($appName, $cotext, $meta);
        $apiDoc = $injector->getInstance(ApiDoc::class);
        /* @var \BEAR\ApiDoc\ApiDoc $apiDoc */
        $apiDoc->setRenderer(new class implements RenderInterface {
            public function render(ResourceObject $ro)
            {
                return new NullResourceObject;
            }
        }); // set twig renderer by self
        $apiDoc->write($docDir);

        return "API Doc is created at {$docDir}" . PHP_EOL;
    }
}
