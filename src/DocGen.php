<?php
namespace BEAR\ApiDoc;

use BEAR\AppMeta\Meta;
use BEAR\Package\AppInjector;
use BEAR\Resource\NullRenderer;
use Ray\Di\AbstractModule;

final class DocGen
{
    
    public function __invoke(
        string $appName,
        string $docDir,
        string $cotext = 'app',
        string $templateClass = Template::class
    ) : string {
        $meta = new Meta($appName, $cotext);
        $injector = new AppInjector($appName, $cotext, $meta);
        $apiDoc = $injector->getInstance(ApiDoc::class);
        $responder = $injector->getOverrideInstance(new class($docDir, $templateClass) extends AbstractModule {
            private $docDir;
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
        }, FileResponder::class);
        /* @var \BEAR\ApiDoc\ApiDoc $apiDoc */
        // set twig renderer by self
        $apiDoc->setRenderer(new NullRenderer);
        $apiDoc->transfer($responder, []);

        return "API Doc is created at {$docDir}" . PHP_EOL;
    }
}
