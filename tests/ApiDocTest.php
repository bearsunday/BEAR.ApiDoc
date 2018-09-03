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
        $this->resource = $resource = (new Injector(
            new JsonSchemaModule(
                $schemaDir,
                '',
                new ResourceModule('FakeVendor\FakeProject')
            ),
            $classDir
        ))->getInstance(ResourceInterface::class);
        $apiDoc = new ApiDoc($resource, $routerContainer, $schemaDir);
        $apiDoc->setRenderer(new JsonRenderer());
        $this->apiDoc = $apiDoc;
    }

    public function testOptions()
    {
        $options = $this->resource->options('app://self/user')->view;
        $expected = '{
    "GET": {
        "request": {
            "parameters": {
                "age": {
                    "type": "integer"
                }
            },
            "required": [
                "age"
            ]
        },
        "schema": {
            "id": "user.json",
            "$schema": "http://json-schema.org/draft-04/schema#",
            "title": "User",
            "type": "object",
            "properties": {
                "firstName": {
                    "type": "string",
                    "maxLength": 30,
                    "pattern": "[a-z\\\\d~+-]+"
                },
                "lastName": {
                    "type": "string",
                    "maxLength": 30,
                    "pattern": "[a-z\\\\d~+-]+"
                },
                "age": {
                    "$ref": "age.json"
                }
            },
            "required": [
                "firstName",
                "lastName",
                "age"
            ]
        }
    },
    "POST": {
        "summary": "Create user",
        "description": "Create user with given name and age",
        "request": {
            "parameters": {
                "name": {
                    "type": "string",
                    "description": "user name"
                },
                "age": {
                    "type": "integer",
                    "description": "user age"
                }
            },
            "required": [
                "name",
                "age"
            ]
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
}
