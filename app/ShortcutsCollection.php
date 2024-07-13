<?php

namespace Shortcuts;

use Shortcuts\ICommand\CallbackWithArgs;
use Shortcuts\ICommand\CommandsCollection;
use Shortcuts\ICommand\CommandWithoutArgs;
use Shortcuts\ICommand\WorkingDir;

abstract class ShortcutsCollection
{
    private readonly ConsoleService $console;

    function init(InjectablesContainer $di): void {}

    function enableRuntimeMode(ConsoleService $console): void
    {
        $this->console = $console;
    }

    /**
     * @return CommandWithoutArgs[]
     */
    function resolveCallbacks(CommandsCollection $commands): \Generator
    {
        if (!isset($this->console)) {
            throw new \Exception('Runtime is not enabled yet');
        }

        foreach ($commands->asArray() as $command) {
            if ($command instanceof CallbackWithArgs) {
                $_commands = (new CommandsCollection);
                $command->composer->call(
                    $this,
                    $_commands,
                    ...$command->populateArgsWithValues($this->console->args)
                );
                yield from $this->resolveCallbacks($_commands);
            } elseif ($command instanceof WorkingDir) {
                $this->console->setCwd($command->dir);
            } elseif ($command instanceof CommandWithoutArgs) {
                yield $command;
            } else {
                throw new \Exception('Unsupported command type: ' . get_class($command));
            }
        }
    }
}
