<?php
namespace BEAR\ApiDoc;

use Aura\Router\RouterContainer;
use BEAR\Resource\JsonRenderer;
use BEAR\Resource\Module\JsonSchemaModule;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use function file_put_contents;

class ApiDocTest extends TestCase
{
    /**
     * @var ApiDoc
     */
    private $apiDoc;

    /**
     * @var ResourceInterface
     */
    private $resource;

    public function setUp()
    {
        $routerContainer = new RouterContainer;
        $schemaDir = __DIR__ . '/Fake/app/var/json_schema';
        $classDir = __DIR__ . '/tmp';
        $routerFile = __DIR__ . '/Fake/app/var/conf/aura.route.php';
        $this->resource = $resource = (new Injector(
            new JsonSchemaModule(
                $schemaDir,
                '',
                new ResourceModule('FakeVendor\FakeProject')
            ),
            $classDir
        ))->getInstance(ResourceInterface::class);
        $apiDoc = new ApiDoc($resource, $schemaDir, new Template, $routerContainer, $routerFile);
        $apiDoc->setRenderer(new JsonRenderer());
        $this->apiDoc = $apiDoc;
    }

    public function testOptions()
    {
        $options = $this->resource->options('app://self/person')->view;
        $expected = '{
    "GET": {
        "request": {
            "parameters": {
                "id": {
                    "type": "string",
                    "description": "The unique ID of the person.",
                    "default": "koriym"
                }
            }
        },
        "schema": {
            "$id": "person.json",
            "$schema": "http://json-schema.org/draft-07/schema#",
            "title": "Person",
            "type": "object",
            "properties": {
                "firstName": {
                    "type": "string",
                    "description": "The person\'s first name."
                },
                "lastName": {
                    "type": "string",
                    "description": "The person\'s last name."
                },
                "age": {
                    "description": "Age in years which must be equal to or greater than zero.",
                    "type": "integer",
                    "minimum": 0
                }
            },
            "additionalProperties": false
        }
    }
}
';
        $this->assertSame($expected, $options);
    }

    public function testGetApiDoc()
    {
        $ro = $this->apiDoc->onGet('user');
        $view = (string) $ro;
        file_put_contents(__DIR__ . '/api_doc.html', $view);
        $this->assertContains('GET', $view);
        $this->assertContains('POST', $view);
        $this->assertContains('Request', $view);
        $this->assertContains('Response', $view);
    }

    public function testIndexPage()
    {
        $ro = $this->apiDoc->onGet();
        $indexHtml = (string) $ro;
        $this->assertContains('<p>Welcome to the our API !<br />', $indexHtml);
    }

    public function testRelPage()
    {
        $ro = $this->apiDoc->onGet('user');
        $relHtml = (string) $ro;
        $this->assertContains('<span>firstName, lastName, age</span>', $relHtml);
    }

    public function testSchemaPage()
    {
        $ro = $this->apiDoc->onGet(null, 'user.json');
        $relHtml = (string) $ro;
        $this->assertContains('<h1>user.json</h1>', $relHtml);
    }
}
