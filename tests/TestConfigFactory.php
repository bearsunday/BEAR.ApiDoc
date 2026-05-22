<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use ReflectionClass;

use function array_replace;

final class TestConfigFactory
{
    /** @param array<string, mixed> $overrides */
    public static function new(array $overrides = []): Config
    {
        $reflection = new ReflectionClass(Config::class);
        $config = $reflection->newInstanceWithoutConstructor();
        $defaults = [
            'appName' => 'FakeVendor\FakeProject',
            'scheme' => 'app',
            'docDir' => __DIR__ . '/docs',
            'formats' => [],
            'title' => '',
            'description' => '',
            'links' => [],
            'alps' => '',
            'resourceFiles' => [],
            'modelRepository' => new ModelRepository(),
            'routes' => [],
            'requestSchemaDir' => '',
            'responseSchemaDir' => '',
            'fakeDataDir' => '',
            'queryClasses' => [],
            'sqlDir' => '',
        ];

        foreach (array_replace($defaults, $overrides) as $name => $value) {
            $reflection->getProperty($name)->setValue($config, $value);
        }

        return $config;
    }
}
