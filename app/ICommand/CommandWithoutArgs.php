<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;

class CommandWithoutArgs implements ICommand
{
    function __construct(public string $command, public bool $echoCommand) {}

    function isEchoRequired(): bool
    {
        return $this->echoCommand;
    }
}
