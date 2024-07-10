<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;
use Shortcuts\ShortcutsCollection;

class WorkingDir implements ICommand
{
    function __construct(public string $dir) {}

    function compose(array $argumentsEscaped, ShortcutsCollection $thisForCallback): string
    {
        return $this->dir;
    }

    function isEchoRequired(): bool
    {
        return false;
    }
}
