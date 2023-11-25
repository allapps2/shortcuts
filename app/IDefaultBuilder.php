<?php

namespace Shortcuts;

use Shortcuts\ShortcutDTO\ShortcutsCollection;

interface IDefaultBuilder
{
    function getShortcuts(array $commandLineArguments): ShortcutsCollection;
    function getEnv(): ?IEnvDTO;
}
