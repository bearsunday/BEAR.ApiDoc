<?php

declare(strict_types=1);

namespace BEAR\ApiDoc;

use Ray\Di\BindingsMarkdown;
use Ray\Di\Container;
use Ray\Di\ModuleVisitorInterface;

/**
 * Captures a module's composed bindings as a markdown snapshot
 *
 * Ray.Di 2.23 moved binding visualization out of core: {@see BindingsMarkdown}
 * renders the composed container, and {@see ModuleVisitorInterface} is the
 * stable route to reach it via {@see \Ray\Di\AbstractModule::accept()}.
 * This visitor bridges the two — collect the container on accept(), render it
 * to markdown in memory (no filesystem round-trip), and expose the snapshot.
 */
final class BindingsSnapshot implements ModuleVisitorInterface
{
    private ?string $markdown = null;

    public function visit(Container $container): void
    {
        $this->markdown = (new BindingsMarkdown())->render($container);
    }

    /** @return non-empty-string|'' */
    public function markdown(): string
    {
        return $this->markdown ?? '';
    }
}
