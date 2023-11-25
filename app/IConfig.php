<?php

namespace Shortcuts;

use Shortcuts\ShortcutDTO\ShortcutsCollection;

interface IConfig
{
    const CONFIG_FILE = 'shortcuts.php';

    function getDefaultShortcutsBuilder(): IDefaultBuilder;
    function getLocalShortcutsBuilder(): ?ILocalBuilder;
    function onBuildComplete(ShortcutsCollection $shortcuts): void;
}
