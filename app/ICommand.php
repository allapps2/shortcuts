<?php

namespace Shortcuts;

interface ICommand
{
    function compose(array $argumentsEscaped): string;
    function isEchoRequired(): bool;
}