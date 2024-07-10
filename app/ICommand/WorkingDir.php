<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;

class WorkingDir implements ICommand
{
    function __construct(public string $dir) {}

    function isEchoRequired(): bool
    {
        return false;
    }
}
