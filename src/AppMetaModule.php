<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Resource\Annotation\AppName;
use BEAR\Sunday\Extension\Application\AppInterface;
use Ray\Di\AbstractModule;

use function assert;
use function class_exists;

class AppMetaModule extends AbstractModule
{
    /** @var AbstractAppMeta */
    private $appMeta;

    public function __construct(AbstractAppMeta $appMeta, ?AbstractModule $module = null)
    {
        $this->appMeta = $appMeta;
        parent::__construct($module);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->bind(AbstractAppMeta::class)->toInstance($this->appMeta);
        $app = $this->appMeta->name . '\Module\App';
        assert(class_exists($app));
        $this->bind(AppInterface::class)->to($app);
        $this->bind()->annotatedWith(AppName::class)->toInstance($this->appMeta->name);
    }
}
