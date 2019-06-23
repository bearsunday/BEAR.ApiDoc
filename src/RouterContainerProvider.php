<?php

namespace BEAR\ApiDoc;

use Aura\Router\RouterContainer;
use BEAR\Package\Provide\Router\RouterCollection;
use Ray\Di\Exception\Unbound;
use Ray\Di\InjectorInterface;
use Ray\Di\ProviderInterface;

class RouterContainerProvider implements ProviderInterface
{
    /**
     * @var RouterCollection
     */
    private $collection;
    /**
     * @var InjectorInterface
     */
    private $injector;

    public function __construct(InjectorInterface $injector)
    {
        $this->injector = $injector;
    }

    public function get()
    {
        try {
            return $this->injector->getInstance(RouterContainer::class);
        } catch (Unbound $e) {
            return null;
        }
    }
}
