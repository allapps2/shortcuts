<?php

namespace Shortcuts;

#[\Attribute]
class ShortcutArg
{
    function __construct(public string $description) {}
}
