#!/usr/bin/env php
<?php

use BEAR\ApiDoc\ApiDoc;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;

        break;
    }
}
$options = getopt('c::');
$fileName = isset($options['c']) && is_string($options['c']) ? $options['c'] : 'apidoc.xml';
$apidocXml = sprintf('%s/%s', getcwd(), $fileName);

(new ApiDoc($apidocXml))();
