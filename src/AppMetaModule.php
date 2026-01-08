<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Resource\Annotation\AppName;
use BEAR\Sunday\Extension\Application\AppInterface;
use Ray\Di\AbstractModule;

use function assert;
use function class_exists;

final class AppMetaModule extends AbstractModule
{
    public function __construct(private readonly AbstractAppMeta $appMeta, ?AbstractModule $module = null)
    {
        parent::__construct($module);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function configure()
    {
        $this->bind(AbstractAppMeta::class)->toInstance($this->appMeta);
        $app = $this->appMeta->name . '\Module\App';
        assert(class_exists($app));
        $this->bind(AppInterface::class)->to($app);
        $this->bind()->annotatedWith(AppName::class)->toInstance($this->appMeta->name);
    }
}
