<?php

declare(strict_types=1);

namespace BEAR\ApiDoc\Exception;

use RuntimeException;

use function sprintf;

final class ConfigNotFoundException extends RuntimeException
{
    public function __construct(string $path)
    {
        $message = $path !== ''
            ? sprintf("%s\nRun 'apidoc init' to create apidoc.xml", $path)
            : "Run 'apidoc init' to create apidoc.xml";

        parent::__construct($message);
    }
}
