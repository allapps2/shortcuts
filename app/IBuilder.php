<?php

namespace Shortcuts;

interface IBuilder
{
    const CONFIG_FILE = 'shortcuts.php';

    function build(ShortcutsCollectionFactory $factory): ShortcutsCollection;
}
