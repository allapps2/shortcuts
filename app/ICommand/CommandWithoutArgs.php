<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;
use Shortcuts\ShortcutsCollection;

class CommandWithoutArgs implements ICommand
{
    function __construct(public string $command, public bool $echoCommand) {}

    function compose(array $argumentsEscaped, ShortcutsCollection $thisForCallback): string
    {
        return $this->command;
    }

    function isEchoRequired(): bool
    {
        return $this->echoCommand;
    }
}
