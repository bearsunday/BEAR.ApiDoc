<?php

namespace BEAR\ApiDoc;

use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use manuelodelain\Twig\Extension\LinkifyExtension;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ArrayLoader;

final class TwigRenderer implements RenderInterface
{
    private $template;

    public function __construct(array $template)
    {
        $this->template = $template;
    }

    public function render(ResourceObject $ro)
    {
        $ro->headers['content-type'] = 'text/html; charset=utf-8';
        $twig = new Environment(new ArrayLoader($this->template), ['debug' => true]);
        $twig->addExtension(new DebugExtension);
        $twig->addExtension(new RefLinkExtension);
        $twig->addExtension(new LinkifyExtension);
        $ro->view = $twig->render('index', $ro->body);

        return $ro->view;
    }
}
