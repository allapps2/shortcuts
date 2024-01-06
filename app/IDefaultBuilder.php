<?php

namespace Shortcuts;

use Shortcuts\ShortcutDTO\ShortcutsCollection;

interface IDefaultBuilder
{
    function getShortcuts(): ShortcutsCollection;
    function getEnv(): ?IEnvDTO;
}
