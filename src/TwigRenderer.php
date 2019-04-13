<?php
namespace BEAR\ApiDoc;

use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use manuelodelain\Twig\Extension\LinkifyExtension;

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
        $twig = new \Twig_Environment(new \Twig_Loader_Array($this->template), ['debug' => true]);
        $twig->addExtension(new \Twig_Extension_Debug);
        $twig->addExtension(new RefLinkExtention);
        $twig->addExtension(new LinkifyExtension);
        $ro->view = $twig->render('index', $ro->body);

        return $ro->view;
    }
}
