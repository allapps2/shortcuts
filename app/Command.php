<?php

namespace Shortcuts;

class Command
{
    function __construct(public string $command, public bool $echoCommand) {}
}
