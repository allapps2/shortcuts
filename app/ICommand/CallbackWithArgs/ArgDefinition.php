<?php

namespace Shortcuts\ICommand\CallbackWithArgs;

#[\Attribute]
class ArgDefinition
{
    function __construct(public string $description) {}
}
