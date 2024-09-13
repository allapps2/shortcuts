<?php

namespace Shortcuts;

use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Shortcuts\ICommand\CallbackWithArgs;
use Shortcuts\ICommand\CommandsCollection;
use Shortcuts\ICommand\CommandWithoutArgs;
use Shortcuts\ICommand\WorkingDir;

abstract class ShortcutsCollection
{
    private readonly ConsoleService $console;
    private array $availableShortcuts;

    function onAfterConstruct(InjectablesContainer $di): void {}
    function onCommandsCompose(string $shortcut, CommandsCollection $commands): void {}

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

    function getAvailableShortcuts(bool $ownMethodsOnly = false): array
    {
        if (!isset($this->availableShortcuts)) {
            $refThis = new ReflectionObject($this);
            $methods = $refThis->getMethods(ReflectionMethod::IS_PUBLIC);
            $forbiddenShortcutNames = array_map(
                fn(ReflectionMethod $refMethod) => $refMethod->getName(),
                (new ReflectionClass(self::class))->getMethods()
            );
            $forbiddenShortcutNames[] = '__construct';
            foreach ($methods as $refMethod) {
                $shortcut = $refMethod->getName();
                if (in_array($shortcut, $forbiddenShortcutNames, true)) {
                    continue;
                }

                if (
                    !$refMethod->getReturnType() ||
                    $refMethod->getReturnType()->getName() !== CommandsCollection::class
                ) {
                    $className = $refThis->isAnonymous()
                        ? $refThis->getFileName()
                        : $refThis->getName();
                    throw new \Exception(
                        "All public methods of {$className} are shortcuts " .
                        "and must return " . CommandsCollection::class .
                        ", please fix {$shortcut}()"
                    );
                }

                if ($commands = $refMethod->invoke($this)) { // null means to skip
                    $this->availableShortcuts[$shortcut] = $commands;
                    $this->onCommandsCompose($shortcut, $commands);
                }
            }

            ksort($this->availableShortcuts);
        }

        return $this->availableShortcuts;
    }
}
