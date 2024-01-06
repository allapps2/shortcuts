<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;

readonly class CommandWithoutArgs implements ICommand
{
    function __construct(public string $command, public bool $echoCommand = true) {}

    function compose(array $argumentsEscaped): string
    {
        return $this->command;
    }

    function isEchoRequired(): bool
    {
        return $this->echoCommand;
    }
}
