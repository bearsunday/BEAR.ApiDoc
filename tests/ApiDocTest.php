<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Aura\Router\RouterContainer;
use BEAR\Resource\Module\JsonSchemaModule;
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

    /**
     * @var ResourceInterface
     */
    private $resource;

    protected function setUp()
    {
        $routerContainer = new RouterContainer;
        $map = $routerContainer->getMap();
        $routerFile = __DIR__ . '/Fake/src/var/conf/aura.route.php';
        require $routerFile;
        $schemaDir = __DIR__ . '/Fake/src/var/json_schema';
        $classDir = __DIR__ . '/tmp';
        $this->resource = $resource = (new Injector(
            new JsonSchemaModule(
                $schemaDir,
                '',
                new ResourceModule('FakeVendor\FakeProject')
            ),
            $classDir
        ))->getInstance(ResourceInterface::class);
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
}
