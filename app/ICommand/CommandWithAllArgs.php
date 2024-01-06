<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;

readonly class CommandWithAllArgs implements ICommand
{
    function __construct(private \Closure $composer, private bool $echoCommand = true) {}

    function compose(array $argumentsEscaped): string
    {
        return $this->composer->call($this, implode(' ', $argumentsEscaped));
    }

    function isEchoRequired(): bool
    {
        return $this->echoCommand;
    }
}