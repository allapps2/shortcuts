<?php

namespace Shortcuts;

interface ICommand
{
    function compose(array $argumentsEscaped, ShortcutsCollection $thisForCallback): string;
    function isEchoRequired(): bool;
}
