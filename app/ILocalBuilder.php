<?php

namespace Shortcuts;

use Shortcuts\ShortcutDTO\ShortcutsCollection;

interface ILocalBuilder
{
    function updateShortcuts(ShortcutsCollection $shortcuts): void;
    function updateEnv(IEnvDTO $dtoEnv): IEnvDTO;
}
