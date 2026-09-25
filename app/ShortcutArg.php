<?php

namespace Shortcuts;

#[\Attribute]
class ShortcutArg
{
    function __construct(
        public string $description,
        // by default string/array values are escapeshellarg()'d before being
        // passed to the shortcut method, since they're usually interpolated
        // into a shell command
        public bool   $escape = true,
    ) {}
}
