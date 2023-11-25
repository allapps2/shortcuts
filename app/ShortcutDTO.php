<?php

namespace Shortcuts;

use Shortcuts\CommandDTO\CommandsCollection;

readonly class ShortcutDTO
{
    function __construct(
        public string             $shortcut,
        public CommandsCollection $commands,
        public ?string            $description = null,
    ) {}
}
