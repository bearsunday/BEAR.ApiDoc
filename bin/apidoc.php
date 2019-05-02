<?php

use BEAR\ApiDoc\DocGen;
use BEAR\ApiDoc\MarkdownTemplate;
use Koriym\Alps\Alps;
use Koriym\Alps\Markdown;
use Koriym\Alps\NullAlps;

require dirname(__DIR__) . '/vendor/autoload.php';

$appName = 'MyVendor\MyProject';
$appDir = dirname(__DIR__);
$docDir = dirname(__DIR__) . '/docs';
$profile = dirname(__DIR__) . './profile.json';
$alps = file_exists($profile) ? new Alps($profile) : new NullAlps;
if ($alps instanceof Alps) {
    file_put_contents($docDir . '/descriptor.md', new Markdown($alps));
}

echo (new DocGen)($appName, realpath($docDir), 'app', MarkdownTemplate::class, $alps);
