<?php

namespace Shortcuts;

readonly class CommandDTO
{
    function __construct(public string $command, public bool $echoCommand = true) {}
}
