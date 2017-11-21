<?php
namespace BEAR\ApiDoc;

use Aura\Router\RouterFactory;
use BEAR\Resource\JsonRenderer;
use BEAR\Resource\Module\JsonSchemalModule;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

class ApiDocTest extends TestCase
{
    /**
     * @var ApiDoc
     */
    private $apiDoc;
    private $resource;

    public function setUp()
    {
        $router = (new RouterFactory)->newInstance();
        $schemaDir = __DIR__ . '/Fake/schema';
        $classDir = __DIR__ . '/tmp';
        $this->resource = $resource = (new Injector(
            new JsonSchemalModule(
                $schemaDir,
                '',
                new ResourceModule('FakeVendor\FakeProject')
            ),
            $classDir
        ))->getInstance(ResourceInterface::class);
        $apiDoc = new ApiDoc($router);
        $apiDoc->setScehmaDir(__DIR__ . '/Fake/schema');
        $apiDoc->setResource($resource);
        $apiDoc->setRenderer(new JsonRenderer());
        $this->apiDoc = $apiDoc;
    }

    public function testRender()
    {
        $ro = $this->apiDoc->onGet('user');
        (string) $ro;
    }
}
