<?php

namespace Shortcuts;

#[\Attribute]
class Shortcut
{
    function __construct(public string $description) {}
}
