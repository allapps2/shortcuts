<?php

namespace Shortcuts;

readonly class ShortcutsCollectionFactory
{
    function __construct(private InjectablesContainer $di) {}

    function create(string $classShortcutsCollection): ShortcutsCollection
    {
        return new $classShortcutsCollection(
            ...$this->di->detectInjections($classShortcutsCollection)
        );
    }
}
