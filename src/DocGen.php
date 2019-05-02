<?php
namespace BEAR\ApiDoc;

use BEAR\AppMeta\Meta;
use BEAR\Package\AppInjector;
use BEAR\Resource\NullRenderer;
use Koriym\Alps\AbstractAlps;
use Koriym\Alps\NullAlps;

final class DocGen
{
    /**
     * @param string $appName       Application name (Vendor\Project)
     * @param string $docDir        Documentation output directory
     * @param string $context       Application context
     * @param string $templateClass Custom template class name
     */
    public function __invoke(
        string $appName,
        string $docDir,
        string $context = 'app',
        string $templateClass = Template::class,
        AbstractAlps $alps = null
    ) : string {
        $meta = new Meta($appName, $context);
        $injector = new AppInjector($appName, $context, $meta);
        $injector->clear();
        $responderModule = new FileResponderModule($docDir, $templateClass);
        $alps = $alps ?? new NullAlps;
        /** @var ApiDoc $apiDoc */
        $apiDoc = $injector->getOverrideInstance(new ApiDocModule($alps, $responderModule), ApiDoc::class);
        /** @var FileResponder $responder */
        $responder = $injector->getOverrideInstance($responderModule, FileResponder::class);
        // set twig renderer by self
        $apiDoc->setRenderer(new NullRenderer);
        $apiDoc->transfer($responder, []);

        return "API Doc is created at {$docDir}" . PHP_EOL;
    }
}
